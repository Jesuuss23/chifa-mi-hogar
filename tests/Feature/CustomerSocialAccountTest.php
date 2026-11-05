<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FacebookCustomerLogin;
use App\Services\GoogleCustomerLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerSocialAccountTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'cache.default' => 'array',
            'google_login.enabled' => false,
            'facebook_login.enabled' => false,
        ]);
        Http::preventStrayRequests();
    }

    public static function providers(): array
    {
        return [['google'], ['facebook']];
    }

    #[DataProvider('providers')]
    public function test_unlink_preserves_direct_access_profile_and_other_provider(string $provider): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'known-password']);
        $other = $provider === 'google' ? 'facebook' : 'google';
        $this->identity($user, $provider);
        $this->identity($user, $other);
        $hash = $user->password;
        $email = $user->email;
        $name = $user->name;
        $this->actingAs($user)->withSession([
            'cart' => ['sample' => 2], 'google_login' => ['state' => 'old'],
            'facebook_login' => ['state' => 'old'],
        ])->delete($this->url($provider), $this->confirmation())
            ->assertRedirect(route('customer.account'))->assertSessionHas('social_status')
            ->assertSessionHas('cart.sample', 2)->assertSessionHas('customer_password_version', 1)
            ->assertSessionMissing('google_login')->assertSessionMissing('facebook_login');
        $this->assertDatabaseMissing('customer_social_accounts', ['user_id' => $user->id, 'provider' => $provider]);
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $user->id, 'provider' => $other]);
        $this->assertSame($hash, $user->fresh()->password);
        $this->assertSame($email, $user->fresh()->email);
        $this->assertSame($name, $user->fresh()->name);
        $this->get(route('customer.account'))->assertOk();
        $this->post(route('customer.logout'));
        $this->post(route('customer.login.store'), ['email' => $email, 'password' => 'known-password'])->assertRedirect();
        $this->assertAuthenticatedAs($user);
        Http::assertNothingSent();
    }

    #[DataProvider('providers')]
    public function test_wrong_password_or_missing_confirmation_never_removes_identity(string $provider): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'known-password']);
        $this->identity($user, $provider);
        $this->actingAs($user)->delete($this->url($provider), [...$this->confirmation(), 'current_password' => 'wrong'])
            ->assertSessionHasErrors(['current_password'], null, 'unlinkSocial');
        $this->delete($this->url($provider), ['current_password' => 'known-password'])
            ->assertSessionHasErrors(['confirm_unlink'], null, 'unlinkSocial');
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $user->id, 'provider' => $provider]);
        $this->assertSame(0, (int) $user->fresh()->password_version);
    }

    public function test_social_only_customer_cannot_remove_the_only_access_with_unknown_password(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => bin2hex(random_bytes(32))]);
        $this->identity($user, 'google');
        $this->actingAs($user)->delete($this->url('google'), $this->confirmation())
            ->assertSessionHasErrors(['current_password'], null, 'unlinkSocial');
        $this->assertDatabaseCount('customer_social_accounts', 1);
    }

    public function test_only_provider_can_be_removed_once_password_is_known(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'known-password']);
        $this->identity($user, 'google');
        $this->actingAs($user)->delete($this->url('google'), $this->confirmation())->assertRedirect();
        $this->assertDatabaseCount('customer_social_accounts', 0);
        $this->assertTrue(Hash::check('known-password', $user->fresh()->password));
    }

    public function test_client_cannot_choose_another_users_identity(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'known-password']);
        $owner = User::factory()->create(['role' => 'customer']);
        $this->identity($owner, 'google');
        $this->actingAs($user)->delete($this->url('google'), [
            ...$this->confirmation(), 'user_id' => $owner->id, 'provider_id' => 'google-id',
        ])->assertRedirect();
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $owner->id, 'provider' => 'google']);
        $this->assertSame(0, (int) $user->fresh()->password_version);
    }

    public function test_repeated_unlink_does_not_rotate_version_again(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'known-password']);
        $this->identity($user, 'google');
        $this->actingAs($user)->delete($this->url('google'), $this->confirmation())->assertRedirect();
        $this->delete($this->url('google'), $this->confirmation())->assertRedirect();
        $this->assertSame(1, (int) $user->fresh()->password_version);
    }

    public function test_guest_staff_and_unknown_provider_cannot_unlink(): void
    {
        $this->delete($this->url('google'), $this->confirmation())->assertRedirect();
        foreach (['admin', 'delivery'] as $role) {
            $staff = User::factory()->create(['role' => $role]);
            $this->actingAs($staff)->delete($this->url('google'), $this->confirmation())->assertForbidden();
        }
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer)->delete('/mi-cuenta/accesos/unknown', $this->confirmation())->assertNotFound();
    }

    public function test_disabled_providers_remain_manageable_from_account(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->identity($user, 'google');
        $this->identity($user, 'facebook');
        $this->actingAs($user)->get(route('customer.account'))->assertOk()
            ->assertSee('Desvincular Google')->assertSee('Desvincular Facebook')
            ->assertSee('Si todavía no tienes contraseña');
    }

    public function test_previous_session_is_invalidated_after_unlink(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'known-password']);
        $this->identity($user, 'google');
        $this->actingAs($user)->delete($this->url('google'), $this->confirmation())->assertRedirect();
        $this->actingAs($user->fresh())->withSession(['customer_password_version' => 0])
            ->get(route('customer.account'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    #[DataProvider('providers')]
    public function test_removed_provider_cannot_silently_reconnect_using_same_email(string $provider): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'known-password']);
        $this->identity($user, $provider);
        $this->actingAs($user)->delete($this->url($provider), $this->confirmation())->assertRedirect();
        try {
            $service = app($provider === 'google' ? GoogleCustomerLogin::class : FacebookCustomerLogin::class);
            $service->resolve(['id' => $provider.'-id', 'email' => $user->email, 'name' => 'Changed'], null);
            $this->fail('A removed identity must not automatically re-link by email.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey($provider, $exception->errors());
        }
        $this->assertDatabaseCount('customer_social_accounts', 0);
        $this->assertDatabaseCount('users', 1);
    }

    #[DataProvider('providers')]
    public function test_link_started_before_unlink_cannot_restore_removed_identity(string $provider): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'known-password']);
        $this->identity($user, $provider);
        $this->actingAs($user)->delete($this->url($provider), $this->confirmation())->assertRedirect();
        $service = app($provider === 'google' ? GoogleCustomerLogin::class : FacebookCustomerLogin::class);
        try {
            $service->resolve(['id' => $provider.'-id', 'email' => $user->email, 'name' => 'Cliente'], $user->id, 0);
            $this->fail('A stale linking attempt must not restore a removed provider.');
        } catch (\Illuminate\Validation\ValidationException $exception) {
            $this->assertArrayHasKey($provider, $exception->errors());
        }
        $this->assertDatabaseCount('customer_social_accounts', 0);
    }

    public function test_unlink_is_rate_limited(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'known-password']);
        $this->identity($user, 'google');
        $this->actingAs($user);
        for ($i = 0; $i < 5; $i++) {
            $this->delete($this->url('google'), [...$this->confirmation(), 'current_password' => 'wrong'])
                ->assertSessionHasErrors(['current_password'], null, 'unlinkSocial');
        }
        $this->delete($this->url('google'), $this->confirmation())->assertTooManyRequests();
        $this->assertDatabaseCount('customer_social_accounts', 1);
    }

    private function identity(User $user, string $provider): void
    {
        DB::table('customer_social_accounts')->insert([
            'user_id' => $user->id, 'provider' => $provider, 'provider_id' => $provider.'-id',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function url(string $provider): string
    {
        return route('customer.social.destroy', $provider);
    }

    private function confirmation(): array
    {
        return ['current_password' => 'known-password', 'confirm_unlink' => '1'];
    }
}
