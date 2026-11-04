<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\CustomerResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class CustomerPasswordTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array', 'mail.default' => 'smtp']);
        Notification::fake();
    }

    public function test_request_sends_customer_email_and_does_not_disclose_existence(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email' => 'Cliente@example.com']);
        $this->get(route('customer.password.request'))->assertOk()->assertSee('Recuperar acceso');
        $this->post(route('customer.password.email'), ['email' => 'cliente@example.com'])->assertSessionHas('password_status');
        $message = session('password_status');
        Notification::assertSentTo($user, CustomerResetPassword::class);
        $this->post(route('customer.password.email'), ['email' => 'unknown@example.com'])
            ->assertSessionHas('password_status', $message);
        $this->assertDatabaseCount('password_reset_tokens', 1);
    }

    public function test_staff_cannot_receive_or_use_customer_reset(): void
    {
        foreach (['admin', 'delivery'] as $role) {
            $user = User::factory()->create(['role' => $role]);
            $oldHash = $user->password;
            $this->post(route('customer.password.email'), ['email' => $user->email])->assertSessionHas('password_status');
            Notification::assertNotSentTo($user, CustomerResetPassword::class);
            $token = Password::broker('users')->createToken($user);
            $this->post(route('customer.password.store'), $this->payload($user, $token))->assertSessionHasErrors('email');
            $this->assertSame($oldHash, $user->fresh()->password);
        }
    }

    public function test_new_password_consumes_token_preserves_cart_and_requires_login(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'remember_token' => 'old-token']);
        $token = Password::broker('users')->createToken($user);
        $this->withSession(['cart' => ['sample' => 2]])
            ->post(route('customer.password.store'), $this->payload($user, $token))
            ->assertRedirect(route('login'))->assertSessionHas('cart.sample', 2);
        $this->assertGuest();
        $this->assertTrue(Hash::check('new-password-123', $user->fresh()->password));
        $this->assertSame(1, (int) $user->fresh()->password_version);
        $this->assertNotSame('old-token', $user->fresh()->remember_token);
        $this->assertDatabaseCount('password_reset_tokens', 0);
        $this->post(route('customer.password.store'), $this->payload($user, $token))->assertSessionHasErrors('email');
        $this->post(route('customer.login.store'), ['email' => $user->email, 'password' => 'new-password-123'])->assertRedirect();
        $this->get(route('customer.account'))->assertOk();
    }

    public function test_expired_and_wrong_tokens_do_not_change_password(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $old = $user->password;
        $token = Password::broker('users')->createToken($user);
        $this->post(route('customer.password.store'), $this->payload($user, 'incorrect'))->assertSessionHasErrors('email');
        $this->travel(61)->minutes();
        $this->post(route('customer.password.store'), $this->payload($user, $token))->assertSessionHasErrors('email');
        $this->assertSame($old, $user->fresh()->password);
    }

    public function test_confirmation_and_length_validation_keep_token_available(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $token = Password::broker('users')->createToken($user);
        $this->post(route('customer.password.store'), [
            ...$this->payload($user, $token), 'password' => 'short', 'password_confirmation' => 'different',
        ])->assertSessionHasErrors('password');
        $this->assertTrue(Password::broker('users')->tokenExists($user, $token));
    }

    public function test_social_account_can_establish_password_without_losing_identity(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        DB::table('customer_social_accounts')->insert([
            'user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'social-id',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $token = Password::broker('users')->createToken($user);
        $this->post(route('customer.password.store'), $this->payload($user, $token))->assertRedirect(route('login'));
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $user->id, 'provider_id' => 'social-id']);
    }

    public function test_change_requires_current_password_and_invalidates_pending_reset(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'original-password']);
        $token = Password::broker('users')->createToken($user);
        $data = ['current_password' => 'wrong', 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];
        $this->actingAs($user)->put(route('customer.password.update'), $data)
            ->assertSessionHasErrors(['current_password'], null, 'changePassword');
        $this->assertTrue(Hash::check('original-password', $user->fresh()->password));
        $this->withSession(['cart' => ['sample' => 2], 'google_login' => ['state' => 'old']])
            ->put(route('customer.password.update'), [...$data, 'current_password' => 'original-password'])
            ->assertRedirect(route('customer.account'))
            ->assertSessionHas('customer_password_version', 1)->assertSessionHas('cart.sample', 2)
            ->assertSessionMissing('google_login');
        $this->assertFalse(Password::broker('users')->tokenExists($user->fresh(), $token));
        $this->get(route('customer.account'))->assertOk();
    }

    public function test_old_session_is_rejected_even_on_public_page_and_cart_is_kept(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $user->forceFill(['password_version' => 1])->save();
        $this->actingAs($user)->withSession(['customer_password_version' => 0, 'cart' => ['sample' => 2]])
            ->get(route('storefront.index'))->assertRedirect(route('login'))->assertSessionHas('cart.sample', 2);
        $this->assertGuest();
    }

    public function test_legacy_session_without_version_is_rejected_after_password_change(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $user->forceFill(['password_version' => 1])->save();
        $this->actingAs($user)->get(route('customer.account'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_staff_cannot_change_password_in_customer_panel(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->actingAs($user)->put(route('customer.password.update'), [])->assertForbidden();
    }

    public function test_notification_url_uses_configured_domain_and_reset_page_is_private(): void
    {
        config(['app.url' => 'https://chifamihogar.com']);
        $user = User::factory()->create(['role' => 'customer']);
        $url = (new CustomerResetPassword('test-token'))->toMail($user)->actionUrl;
        $this->assertSame('chifamihogar.com', parse_url($url, PHP_URL_HOST));
        $this->get(route('customer.password.reset', ['token' => 'test-token', 'email' => $user->email]))
            ->assertOk()->assertHeader('Referrer-Policy', 'no-referrer')->assertSee('noindex,nofollow', false);
    }

    public function test_reset_email_is_throttled_and_public_route_has_limit(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('customer.password.email'), ['email' => $user->email])->assertRedirect();
        }
        Notification::assertSentToTimes($user, CustomerResetPassword::class, 1);
        $this->post(route('customer.password.email'), ['email' => $user->email])->assertTooManyRequests();
    }

    public function test_log_transport_never_receives_a_reset_token(): void
    {
        config(['mail.default' => 'log']);
        $user = User::factory()->create(['role' => 'customer']);
        $this->post(route('customer.password.email'), ['email' => $user->email])->assertStatus(503);
        Notification::assertNothingSent();
        $this->assertDatabaseCount('password_reset_tokens', 0);
    }

    public function test_token_cannot_reset_another_customer(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer']);
        $oldHash = $other->password;
        $token = Password::broker('users')->createToken($owner);
        $this->post(route('customer.password.store'), $this->payload($other, $token))->assertSessionHasErrors('email');
        $this->assertSame($oldHash, $other->fresh()->password);
        $this->assertTrue(Password::broker('users')->tokenExists($owner, $token));
    }

    public function test_transport_failure_is_generic_and_does_not_expose_details(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        Notification::shouldReceive('send')->once()->andThrow(
            new \Symfony\Component\Mailer\Exception\TransportException('private-smtp-detail')
        );
        $this->post(route('customer.password.email'), ['email' => $user->email])
            ->assertRedirect()->assertSessionHas('password_status');
        $this->get(route('customer.password.request'))->assertDontSee('private-smtp-detail');
    }

    private function payload(User $user, string $token): array
    {
        return ['email' => $user->email, 'token' => $token, 'password' => 'new-password-123', 'password_confirmation' => 'new-password-123'];
    }
}
