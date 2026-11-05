<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\FacebookCustomerLogin;
use App\Services\GoogleCustomerLogin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CustomerCheckoutReturnTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'cache.default' => 'array', 'turnstile.enabled' => false,
            'google_login.enabled' => true, 'google_login.client_id' => 'test-id',
            'google_login.client_secret' => 'test-secret',
            'google_login.redirect_uri' => 'https://chifamihogar.com/auth/google/callback',
            'facebook_login.enabled' => true, 'facebook_login.app_id' => '123456',
            'facebook_login.app_secret' => 'test-secret', 'facebook_login.graph_version' => 'v24.0',
            'facebook_login.redirect_uri' => 'https://chifamihogar.com/auth/facebook/callback',
        ]);
        Http::preventStrayRequests();
    }

    public function test_password_login_returns_to_checkout_and_preserves_cart_and_token(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'correct-password']);
        $this->begin();
        $this->post(route('customer.login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('cart.checkout'))
            ->assertSessionHas('cart.sample.quantity', 2)->assertSessionHas('checkout_token', 'keep-token')
            ->assertSessionMissing('customer_checkout_return');
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_returns_to_checkout_without_requiring_profile(): void
    {
        $this->begin('customer.register');
        $this->post(route('customer.register.store'), [
            'name' => 'Cliente', 'email' => 'new@example.com',
            'password' => 'correct-password', 'password_confirmation' => 'correct-password',
        ])->assertRedirect(route('cart.checkout'))->assertSessionHas('cart.sample.quantity', 2);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'new@example.com', 'role' => 'customer']);
    }

    public function test_failed_password_attempt_and_switch_to_registration_keep_return_context(): void
    {
        $this->begin();
        $this->post(route('customer.login.store'), ['email' => 'missing@example.com', 'password' => 'wrong'])
            ->assertSessionHasErrors('email')->assertSessionHas('customer_checkout_return');
        $this->get(route('customer.register'))->assertOk()->assertSee('volverás a tu pedido');
        $this->assertSame(2, session('cart.sample.quantity'));
    }

    #[DataProvider('providers')]
    public function test_social_login_returns_to_checkout_and_keeps_cart(string $provider): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->begin();
        $this->fakeProvider($provider, $user);
        $this->withSession([$provider.'_login' => $this->pending()])
            ->get(route('customer.'.$provider.'.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect(route('cart.checkout'))->assertSessionHas('cart.sample.quantity', 2)
            ->assertSessionHas('checkout_token', 'keep-token')->assertSessionMissing('customer_checkout_return');
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('providers')]
    public function test_social_linking_stays_in_account_even_with_checkout_context(string $provider): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->fakeProvider($provider, $user);
        $this->actingAs($user)->withSession([
            'customer_checkout_return' => now()->timestamp,
            'cart' => ['sample' => ['quantity' => 2]],
            $provider.'_login' => $this->pending($user->id),
        ])->get(route('customer.'.$provider.'.callback', ['state' => 'state', 'code' => 'code']))
            ->assertRedirect(route('customer.account'));
    }

    #[DataProvider('providers')]
    public function test_cancelled_social_login_keeps_checkout_available(string $provider): void
    {
        $this->begin();
        $this->withSession([$provider.'_login' => $this->pending()])
            ->get(route('customer.'.$provider.'.callback', ['state' => 'state', 'error' => 'access_denied']))
            ->assertRedirect(route('login'))->assertSessionHas('customer_checkout_return');
        $this->get(route('login'))->assertOk()->assertSee('Volver al pedido sin iniciar sesión');
        Http::assertNothingSent();
    }

    #[DataProvider('invalidContexts')]
    public function test_invalid_or_expired_context_returns_to_account(mixed $context): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'correct-password']);
        $value = match ($context) {
            'expired' => now()->timestamp - 1801,
            'future' => now()->timestamp + 1,
            default => $context,
        };
        $this->withSession(['customer_checkout_return' => $value, 'cart' => ['sample' => ['quantity' => 2]]])
            ->post(route('customer.login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('customer.account'))->assertSessionMissing('customer_checkout_return');
    }

    public static function invalidContexts(): array
    {
        return [['expired'], ['future'], ['https://example.com'], [['route' => 'admin.dashboard']]];
    }

    public function test_empty_cart_during_login_returns_to_cart(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'correct-password']);
        $this->begin();
        $this->withSession(['cart' => []])->post(route('customer.login.store'), [
            'email' => $user->email, 'password' => 'correct-password',
        ])->assertRedirect(route('cart.index'));
    }

    public function test_arbitrary_destination_and_form_parameters_are_not_accepted(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'correct-password']);
        $this->withSession(['cart' => ['sample' => ['quantity' => 2]]])
            ->get(route('login', ['continue' => 'https://example.com']))->assertOk()
            ->assertSessionMissing('customer_checkout_return');
        $this->post(route('customer.login.store'), [
            'email' => $user->email, 'password' => 'correct-password',
            'continue' => 'checkout', 'return_to' => 'https://example.com',
        ])->assertRedirect(route('customer.account'));
    }

    public function test_checkout_marker_does_not_capture_without_cart(): void
    {
        $this->get(route('login', ['continue' => 'checkout']))->assertOk()
            ->assertSessionMissing('customer_checkout_return');
    }

    public function test_normal_login_keeps_existing_intended_destination(): void
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'correct-password']);
        $this->withSession(['url.intended' => route('customer.account')])
            ->post(route('customer.login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('customer.account'));
    }

    public static function providers(): array
    {
        return [['google'], ['facebook']];
    }

    private function begin(string $route = 'login'): void
    {
        $this->withSession(['cart' => ['sample' => ['quantity' => 2]], 'checkout_token' => 'keep-token'])
            ->get(route($route, ['continue' => 'checkout']))->assertOk()->assertSessionHas('customer_checkout_return');
    }

    private function pending(?int $userId = null): array
    {
        return ['state' => 'state', 'verifier' => 'verifier', 'created_at' => now()->timestamp,
            'user_id' => $userId, 'access_version' => $userId === null ? null : 0];
    }

    private function fakeProvider(string $provider, User $user): void
    {
        $class = $provider === 'google' ? GoogleCustomerLogin::class : FacebookCustomerLogin::class;
        $this->mock($class, function ($mock) use ($user) {
            $mock->shouldReceive('profile')->once()->andReturn(['id' => 'id', 'email' => $user->email, 'name' => $user->name]);
            $mock->shouldReceive('resolve')->once()->andReturn($user);
        });
    }
}
