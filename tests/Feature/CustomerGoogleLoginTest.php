<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerGoogleLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'cache.default' => 'array',
            'google_login.enabled' => true,
            'google_login.client_id' => 'test-client',
            'google_login.client_secret' => 'test-secret',
            'google_login.redirect_uri' => 'https://chifamihogar.com/auth/google/callback',
        ]);
        Http::preventStrayRequests();
    }

    public function test_disabled_provider_is_hidden_and_routes_are_unavailable(): void
    {
        config(['google_login.enabled' => false]);
        $this->get(route('login'))->assertOk()->assertDontSee('Continuar con Google');
        $this->get(route('customer.register'))->assertOk()->assertDontSee('Continuar con Google');
        $this->post(route('customer.google.start'))->assertNotFound();
        $this->get(route('customer.google.callback'))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_incomplete_configuration_does_not_show_button(): void
    {
        config(['google_login.client_secret' => null]);
        $this->get(route('login'))->assertOk()->assertDontSee('Continuar con Google');
        $this->post(route('customer.google.start'))->assertNotFound();
    }

    public function test_start_uses_state_pkce_and_minimal_scopes(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Continuar con Google');
        $response = $this->post(route('customer.google.start'))->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertSame('accounts.google.com', parse_url($url, PHP_URL_HOST));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $pending = session('google_login');
        $this->assertSame($pending['state'], $query['state']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertSame(rtrim(strtr(base64_encode(hash('sha256', $pending['verifier'], true)), '+/', '-_'), '='), $query['code_challenge']);
        $this->assertSame('openid email profile', $query['scope']);
        $this->assertArrayNotHasKey('client_secret', $query);
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        Http::assertNothingSent();
    }

    #[DataProvider('invalidCallbacks')]
    public function test_invalid_callback_never_contacts_google(array $query, int $age): void
    {
        $this->withSession(['google_login' => $this->pending(null, $age)])
            ->get(route('customer.google.callback', $query))
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('customer_social_accounts', 0);
        Http::assertNothingSent();
    }

    public static function invalidCallbacks(): array
    {
        return [
            'state missing' => [['code' => 'code'], 0],
            'state mismatch' => [['state' => 'other', 'code' => 'code'], 0],
            'state array' => [['state' => ['state'], 'code' => 'code'], 0],
            'expired' => [['state' => 'state', 'code' => 'code'], 601],
            'future' => [['state' => 'state', 'code' => 'code'], -1],
            'cancelled' => [['state' => 'state', 'error' => 'access_denied'], 0],
            'code missing' => [['state' => 'state'], 0],
        ];
    }

    public function test_new_google_customer_preserves_cart_and_can_login_again(): void
    {
        $this->fakeGoogle();
        $this->withSession(['google_login' => $this->pending(), 'cart' => ['sample' => ['quantity' => 2]]])
            ->get($this->callbackUrl())->assertRedirect(route('customer.account'))
            ->assertSessionHas('cart.sample.quantity', 2)
            ->assertSessionMissing('google_login');
        $user = User::where('email', 'cliente@gmail.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('customer', $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertNotEmpty($user->password);
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'google-id']);
        Http::assertSent(fn ($request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['code_verifier'] === str_repeat('v', 64)
            && $request['redirect_uri'] === config('google_login.redirect_uri'));

        $this->post(route('customer.logout'));
        $this->withSession(['google_login' => $this->pending(null, 0, 'next-state')])
            ->get($this->callbackUrl('next-state'))->assertRedirect(route('customer.account'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customer_social_accounts', 1);
    }

    public function test_state_cannot_be_replayed_even_if_old_session_data_is_restored(): void
    {
        $this->fakeGoogle();
        $pending = $this->pending();
        $this->withSession(['google_login' => $pending])->get($this->callbackUrl())->assertRedirect(route('customer.account'));
        $this->post(route('customer.logout'));
        Http::fake(); // Must never be used by replay.
        $this->withSession(['google_login' => $pending])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        Http::assertNothingSent();
        $this->assertGuest();
    }

    #[DataProvider('roles')]
    public function test_matching_email_never_automatically_links_any_existing_account(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'email' => 'Cliente@gmail.com']);
        $password = $user->password;
        $this->fakeGoogle();
        $this->withSession(['google_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customer_social_accounts', 0);
        $this->assertSame($password, $user->fresh()->password);
    }

    public static function roles(): array
    {
        return [['customer'], ['admin'], ['delivery']];
    }

    public function test_existing_customer_can_link_only_after_password_confirmation(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email' => 'cliente@gmail.com', 'password' => 'correct-password']);
        $this->actingAs($user)->post(route('customer.google.link'), ['password' => 'wrong'])
            ->assertRedirect(route('customer.account'))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('customer_social_accounts', 0);
        $response = $this->post(route('customer.google.link'), ['password' => 'correct-password'])->assertRedirect();
        $pending = session('google_login');
        $this->assertSame($user->id, $pending['user_id']);
        $this->fakeGoogle();
        $this->get($this->callbackUrl($pending['state']))->assertRedirect(route('customer.account'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $user->id, 'provider_id' => 'google-id']);
        $this->assertTrue(Hash::check('correct-password', $user->fresh()->password));
        $this->get(route('customer.account'))->assertOk()->assertSee('Tu cuenta está vinculada');
    }

    public function test_linking_requires_matching_email(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email' => 'other@example.com']);
        $this->fakeGoogle();
        $this->actingAs($user)->withSession(['google_login' => $this->pending($user->id)])
            ->get($this->callbackUrl())->assertRedirect(route('customer.account'))->assertSessionHasErrors('google');
        $this->assertDatabaseCount('customer_social_accounts', 0);
    }

    public function test_linked_staff_account_cannot_login_through_customer_google(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->identity($user);
        $this->fakeGoogle();
        $this->withSession(['google_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
    }

    public function test_linked_identity_cannot_be_moved_to_another_customer(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer', 'email' => 'cliente@gmail.com']);
        $this->identity($owner);
        $this->fakeGoogle();
        $this->actingAs($other)->withSession(['google_login' => $this->pending($other->id)])
            ->get($this->callbackUrl())->assertRedirect(route('customer.account'))->assertSessionHasErrors('google');
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $owner->id, 'provider_id' => 'google-id']);
    }

    public function test_session_user_change_invalidates_callback_before_http(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user)->withSession(['google_login' => $this->pending()])
            ->get($this->callbackUrl())->assertSessionHasErrors('google');
        Http::assertNothingSent();
    }

    #[DataProvider('badProfiles')]
    public function test_invalid_google_profiles_cannot_create_accounts(array $overrides): void
    {
        $this->fakeGoogle($overrides);
        $this->withSession(['google_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public static function badProfiles(): array
    {
        return [
            [['email_verified' => false]],
            [['email_verified' => 'true']],
            [['email' => 'invalid']],
            [['sub' => '']],
            [['sub' => ['invalid']]],
        ];
    }

    public function test_provider_failure_shows_generic_error_without_tokens(): void
    {
        Http::fake(['https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'secret-token', 'error' => 'sensitive-detail'], 400)]);
        $this->withSession(['google_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->get(route('login'))->assertDontSee('secret-token')->assertDontSee('sensitive-detail');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_network_failure_does_not_create_customer(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('private-network-detail'));
        $this->withSession(['google_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('google');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get(route('login'))->assertDontSee('private-network-detail');
    }

    public function test_returning_user_is_identified_by_subject_not_new_email(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email' => 'old@example.com']);
        $this->identity($user);
        $this->fakeGoogle(['email' => 'new@gmail.com']);
        $this->withSession(['google_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('customer.account'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('old@example.com', $user->fresh()->email);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_staff_cannot_start_customer_linking(): void
    {
        $user = User::factory()->create(['role' => 'delivery']);
        $this->actingAs($user)->post(route('customer.google.link'), ['password' => 'password'])
            ->assertForbidden();
        Http::assertNothingSent();
    }

    private function pending(?int $userId = null, int $age = 0, string $state = 'state'): array
    {
        return ['state' => $state, 'verifier' => str_repeat('v', 64), 'created_at' => now()->timestamp - $age, 'user_id' => $userId];
    }

    private function callbackUrl(string $state = 'state'): string
    {
        return route('customer.google.callback', ['state' => $state, 'code' => 'auth-code']);
    }

    private function fakeGoogle(array $overrides = []): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(['access_token' => 'test-access']),
            'https://openidconnect.googleapis.com/v1/userinfo' => Http::response(array_replace([
                'sub' => 'google-id', 'email' => 'cliente@gmail.com', 'email_verified' => true,
                'name' => 'Cliente Google', 'role' => 'admin',
            ], $overrides)),
        ]);
    }

    private function identity(User $user): void
    {
        DB::table('customer_social_accounts')->insert([
            'user_id' => $user->id, 'provider' => 'google', 'provider_id' => 'google-id',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
