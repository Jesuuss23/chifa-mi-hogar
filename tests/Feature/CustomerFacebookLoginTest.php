<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerFacebookLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'cache.default' => 'array',
            'facebook_login.enabled' => true,
            'facebook_login.app_id' => '123456',
            'facebook_login.graph_version' => 'v24.0',
            'facebook_login.app_secret' => 'test-secret',
            'facebook_login.redirect_uri' => 'https://chifamihogar.com/auth/facebook/callback',
        ]);
        Http::preventStrayRequests();
    }

    public function test_disabled_provider_is_hidden_and_routes_are_unavailable(): void
    {
        config(['facebook_login.enabled' => false]);
        $this->get(route('login'))->assertOk()->assertDontSee('Continuar con Facebook');
        $this->get(route('customer.register'))->assertOk()->assertDontSee('Continuar con Facebook');
        $this->post(route('customer.facebook.start'))->assertNotFound();
        $this->get(route('customer.facebook.callback'))->assertNotFound();
        Http::assertNothingSent();
    }

    public function test_incomplete_configuration_does_not_show_button(): void
    {
        config(['facebook_login.app_secret' => null]);
        $this->get(route('login'))->assertOk()->assertDontSee('Continuar con Facebook');
        $this->post(route('customer.facebook.start'))->assertNotFound();
    }

    public function test_start_uses_state_and_only_profile_email_scopes(): void
    {
        $this->get(route('login'))->assertOk()->assertSee('Continuar con Facebook');
        $response = $this->post(route('customer.facebook.start'))->assertRedirect();
        $url = $response->headers->get('Location');
        $this->assertSame('www.facebook.com', parse_url($url, PHP_URL_HOST));
        $this->assertSame('/v24.0/dialog/oauth', parse_url($url, PHP_URL_PATH));
        parse_str(parse_url($url, PHP_URL_QUERY), $query);
        $pending = session('facebook_login');
        $this->assertSame($pending['state'], $query['state']);
        $this->assertSame('public_profile,email', $query['scope']);
        $this->assertSame('code', $query['response_type']);
        $this->assertArrayNotHasKey('client_secret', $query);
        $response->assertHeader('Referrer-Policy', 'no-referrer');
        Http::assertNothingSent();
    }

    #[DataProvider('invalidCallbacks')]
    public function test_invalid_callback_never_contacts_facebook(array $query, int $age): void
    {
        $this->withSession(['facebook_login' => $this->pending(null, $age)])
            ->get(route('customer.facebook.callback', $query))
            ->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
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

    public function test_new_facebook_customer_preserves_cart_and_can_login_again(): void
    {
        $this->fakeFacebook();
        $this->withSession(['facebook_login' => $this->pending(), 'cart' => ['sample' => ['quantity' => 2]]])
            ->get($this->callbackUrl())->assertRedirect(route('customer.account'))
            ->assertSessionHas('cart.sample.quantity', 2)
            ->assertSessionMissing('facebook_login');
        $user = User::where('email', 'cliente@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertSame('customer', $user->role);
        $this->assertNull($user->email_verified_at);
        $this->assertNotEmpty($user->password);
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => '12345']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/oauth/access_token')
            && $request['redirect_uri'] === config('facebook_login.redirect_uri'));
        Http::assertSent(fn ($request) => str_contains($request->url(), '/me?')
            && $request['fields'] === 'id,name,email'
            && $request['appsecret_proof'] === hash_hmac('sha256', 'test-access', 'test-secret'));

        $this->post(route('customer.logout'));
        $this->withSession(['facebook_login' => $this->pending(null, 0, 'next-state')])
            ->get($this->callbackUrl('next-state'))->assertRedirect(route('customer.account'));
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('customer_social_accounts', 1);
    }

    public function test_state_cannot_be_replayed_even_if_old_session_data_is_restored(): void
    {
        $this->fakeFacebook();
        $pending = $this->pending();
        $this->withSession(['facebook_login' => $pending])->get($this->callbackUrl())->assertRedirect(route('customer.account'));
        $this->post(route('customer.logout'));
        Http::fake(); // Must never be used by replay.
        $this->withSession(['facebook_login' => $pending])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
        Http::assertNothingSent();
        $this->assertGuest();
    }

    #[DataProvider('roles')]
    public function test_matching_email_never_automatically_links_any_existing_account(string $role): void
    {
        $user = User::factory()->create(['role' => $role, 'email' => 'Cliente@example.com']);
        $password = $user->password;
        $this->fakeFacebook();
        $this->withSession(['facebook_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
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
        $user = User::factory()->create(['role' => 'customer', 'email' => 'cliente@example.com', 'password' => 'correct-password']);
        $this->actingAs($user)->post(route('customer.facebook.link'), ['password' => 'wrong'])
            ->assertRedirect(route('customer.account'))->assertSessionHasErrors('facebook');
        $this->assertDatabaseCount('customer_social_accounts', 0);
        $response = $this->post(route('customer.facebook.link'), ['password' => 'correct-password'])->assertRedirect();
        $pending = session('facebook_login');
        $this->assertSame($user->id, $pending['user_id']);
        $this->fakeFacebook();
        $this->get($this->callbackUrl($pending['state']))->assertRedirect(route('customer.account'));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $user->id, 'provider_id' => '12345']);
        $this->assertTrue(Hash::check('correct-password', $user->fresh()->password));
        $this->get(route('customer.account'))->assertOk()->assertSee('Tu cuenta está vinculada');
    }

    public function test_linking_requires_matching_email(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email' => 'other@example.com']);
        $this->fakeFacebook();
        $this->actingAs($user)->withSession(['facebook_login' => $this->pending($user->id)])
            ->get($this->callbackUrl())->assertRedirect(route('customer.account'))->assertSessionHasErrors('facebook');
        $this->assertDatabaseCount('customer_social_accounts', 0);
    }

    public function test_linked_staff_account_cannot_login_through_customer_facebook(): void
    {
        $user = User::factory()->create(['role' => 'admin']);
        $this->identity($user);
        $this->fakeFacebook();
        $this->withSession(['facebook_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
        $this->assertGuest();
    }

    public function test_linked_identity_cannot_be_moved_to_another_customer(): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $other = User::factory()->create(['role' => 'customer', 'email' => 'cliente@example.com']);
        $this->identity($owner);
        $this->fakeFacebook();
        $this->actingAs($other)->withSession(['facebook_login' => $this->pending($other->id)])
            ->get($this->callbackUrl())->assertRedirect(route('customer.account'))->assertSessionHasErrors('facebook');
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $owner->id, 'provider_id' => '12345']);
    }

    public function test_session_user_change_invalidates_callback_before_http(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->actingAs($user)->withSession(['facebook_login' => $this->pending()])
            ->get($this->callbackUrl())->assertSessionHasErrors('facebook');
        Http::assertNothingSent();
    }

    #[DataProvider('badProfiles')]
    public function test_invalid_facebook_profiles_cannot_create_accounts(array $overrides): void
    {
        $this->fakeFacebook($overrides);
        $this->withSession(['facebook_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public static function badProfiles(): array
    {
        return [
            [['email' => null]],
            [['email' => 'invalid']],
            [['id' => '']],
            [['id' => ['invalid']]],
        ];
    }

    public function test_provider_failure_shows_generic_error_without_tokens(): void
    {
        Http::fake(['https://graph.facebook.com/v24.0/oauth/access_token*' => Http::response(['access_token' => 'secret-token', 'error' => 'sensitive-detail'], 400)]);
        $this->withSession(['facebook_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
        $this->get(route('login'))->assertDontSee('secret-token')->assertDontSee('sensitive-detail');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_network_failure_does_not_create_customer(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('private-network-detail'));
        $this->withSession(['facebook_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
        $this->get(route('login'))->assertDontSee('private-network-detail');
    }

    public function test_returning_user_can_login_without_email_and_keeps_local_profile(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'email' => 'old@example.com']);
        $this->identity($user);
        $this->fakeFacebook(['email' => null]);
        $this->withSession(['facebook_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('customer.account'));
        $this->assertAuthenticatedAs($user);
        $this->assertSame('old@example.com', $user->fresh()->email);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_staff_cannot_start_customer_linking(): void
    {
        $user = User::factory()->create(['role' => 'delivery']);
        $this->actingAs($user)->post(route('customer.facebook.link'), ['password' => 'password'])
            ->assertForbidden();
        Http::assertNothingSent();
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_token_metadata_never_reaches_profile(array $metadata): void
    {
        $this->fakeFacebook([], $metadata);
        $this->withSession(['facebook_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
        Http::assertNotSent(fn ($request) => str_contains($request->url(), '/me?'));
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public static function invalidTokens(): array
    {
        return [
            'invalid' => [['is_valid' => false]],
            'invalid type' => [['is_valid' => 'true']],
            'wrong app' => [['app_id' => '654321']],
            'malformed app' => [['app_id' => []]],
            'page token' => [['type' => 'PAGE']],
            'missing user' => [['user_id' => null]],
            'expired token' => [['expires_at' => 1]],
            'missing expiry' => [['expires_at' => null]],
            'expired data access' => [['data_access_expires_at' => 1]],
        ];
    }

    public function test_profile_id_must_match_debugged_user(): void
    {
        $this->fakeFacebook(['id' => '67890']);
        $this->withSession(['facebook_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_facebook_callback_does_not_accept_google_session_state(): void
    {
        $this->withSession(['google_login' => $this->pending()])
            ->get($this->callbackUrl())->assertRedirect(route('login'))->assertSessionHasErrors('facebook');
        Http::assertNothingSent();
    }

    public function test_unknown_graph_version_disables_provider(): void
    {
        config(['facebook_login.graph_version' => 'v24.0/../other']);
        $this->get(route('login'))->assertOk()->assertDontSee('Continuar con Facebook');
        $this->post(route('customer.facebook.start'))->assertNotFound();
    }

    public function test_google_identity_with_same_id_does_not_authenticate_as_facebook(): void
    {
        $owner = User::factory()->create(['role' => 'customer', 'email' => 'google-owner@example.com']);
        DB::table('customer_social_accounts')->insert([
            'user_id' => $owner->id, 'provider' => 'google', 'provider_id' => '12345',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $this->fakeFacebook();
        $this->withSession(['facebook_login' => $this->pending()])->get($this->callbackUrl())
            ->assertRedirect(route('customer.account'));
        $facebookUser = User::where('email', 'cliente@example.com')->firstOrFail();
        $this->assertAuthenticatedAs($facebookUser);
        $this->assertNotSame($owner->id, $facebookUser->id);
        $this->assertDatabaseHas('customer_social_accounts', ['user_id' => $owner->id, 'provider' => 'google']);
        $this->assertDatabaseCount('customer_social_accounts', 2);
    }

    private function pending(?int $userId = null, int $age = 0, string $state = 'state'): array
    {
        return ['state' => $state, 'created_at' => now()->timestamp - $age, 'user_id' => $userId];
    }

    private function callbackUrl(string $state = 'state'): string
    {
        return route('customer.facebook.callback', ['state' => $state, 'code' => 'auth-code']);
    }

    private function fakeFacebook(array $overrides = [], array $metadataOverrides = []): void
    {
        Http::fake([
            'https://graph.facebook.com/v24.0/oauth/access_token*' => Http::response(['access_token' => 'test-access']),
            'https://graph.facebook.com/v24.0/debug_token*' => Http::response(['data' => array_replace([
                'app_id' => '123456', 'user_id' => '12345', 'is_valid' => true,
                'type' => 'USER', 'expires_at' => now()->timestamp + 3600,
                'data_access_expires_at' => now()->timestamp + 3600,
            ], $metadataOverrides)]),
            'https://graph.facebook.com/v24.0/me*' => Http::response(array_replace([
                'id' => '12345', 'email' => 'cliente@example.com',
                'name' => 'Cliente Facebook', 'role' => 'admin',
            ], $overrides)),
        ]);
    }

    private function identity(User $user): void
    {
        DB::table('customer_social_accounts')->insert([
            'user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => '12345',
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }
}
