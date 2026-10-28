<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RegistrationTurnstileTest extends TestCase
{
    use RefreshDatabase;

    private const VERIFY_URL = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'app.key' => 'base64:'.base64_encode(str_repeat('a', 32)),
            'turnstile.enabled' => true,
            'turnstile.site_key' => 'test-site-key',
            'turnstile.secret_key' => 'test-secret-key',
            'turnstile.hostnames' => ['chifamihogar.com', 'www.chifamihogar.com'],
        ]);
        Cache::flush();
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Cliente',
            'email' => 'customer@example.com',
            'password' => 'test-password-123',
            'password_confirmation' => 'test-password-123',
            'cf-turnstile-response' => 'test-token',
        ], $overrides);
    }

    public function test_verified_registration_creates_only_customer_and_logs_in(): void
    {
        Http::fake([self::VERIFY_URL => Http::response([
            'success' => true, 'hostname' => 'chifamihogar.com', 'action' => 'register',
        ])]);
        $this->post(route('customer.register.store'), $this->data(['role' => 'admin']))
            ->assertRedirect(route('customer.account'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('users', ['email' => 'customer@example.com', 'role' => 'customer']);
        $this->assertAuthenticated();
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === self::VERIFY_URL
            && $request['secret'] === 'test-secret-key'
            && $request['response'] === 'test-token');
    }

    public static function rejectedResponses(): array
    {
        return [
            'rejected' => [['success' => false], 200],
            'expired or reused' => [['success' => false, 'error-codes' => ['timeout-or-duplicate']], 200],
            'wrong hostname' => [['success' => true, 'hostname' => 'other.example', 'action' => 'register'], 200],
            'wrong action' => [['success' => true, 'hostname' => 'chifamihogar.com', 'action' => 'login'], 200],
            'missing metadata' => [['success' => true], 200],
            'string success' => [['success' => 'true', 'hostname' => 'chifamihogar.com', 'action' => 'register'], 200],
            'server error' => [['success' => true, 'hostname' => 'chifamihogar.com', 'action' => 'register'], 500],
            'malformed body' => ['not json', 200],
        ];
    }

    #[DataProvider('rejectedResponses')]
    public function test_invalid_verification_never_creates_a_user(array|string $body, int $status): void
    {
        Http::fake([self::VERIFY_URL => Http::response($body, $status)]);
        $this->post(route('customer.register.store'), $this->data())
            ->assertSessionHasErrors('cf-turnstile-response')
            ->assertSessionMissing('_old_input.cf-turnstile-response')
            ->assertSessionMissing('_old_input.password')
            ->assertSessionMissing('_old_input.password_confirmation');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
        Http::assertSentCount(1);
    }

    public static function invalidTokens(): array
    {
        return [
            'missing' => [null],
            'array' => [['unexpected']],
            'too long' => [str_repeat('a', 2049)],
        ];
    }

    #[DataProvider('invalidTokens')]
    public function test_invalid_token_shape_is_rejected_before_http(mixed $token): void
    {
        $this->post(route('customer.register.store'), $this->data(['cf-turnstile-response' => $token]))
            ->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseCount('users', 0);
        Http::assertNothingSent();
    }

    public function test_network_failure_does_not_bypass_verification(): void
    {
        Http::fake(fn () => throw new ConnectionException('Simulated timeout'));
        $this->post(route('customer.register.store'), $this->data())
            ->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public static function missingConfiguration(): array
    {
        return ['secret' => ['secret_key', ''], 'site' => ['site_key', ''], 'hosts' => ['hostnames', []]];
    }

    #[DataProvider('missingConfiguration')]
    public function test_enabled_but_incomplete_configuration_blocks_registration(string $key, mixed $value): void
    {
        config(['turnstile.'.$key => $value]);
        $this->post(route('customer.register.store'), $this->data())
            ->assertSessionHasErrors('cf-turnstile-response');
        $this->assertDatabaseCount('users', 0);
        Http::assertNothingSent();
    }

    public function test_explicitly_disabled_mode_preserves_registration_without_http(): void
    {
        config(['turnstile.enabled' => false]);
        $this->post(route('customer.register.store'), $this->data(['cf-turnstile-response' => null]))
            ->assertRedirect(route('customer.account'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('users', 1);
        $this->assertAuthenticated();
        Http::assertNothingSent();
    }

    public function test_widget_only_loads_when_enabled_and_never_exposes_secret(): void
    {
        $this->get(route('customer.register'))->assertOk()
            ->assertSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false)
            ->assertSee('data-action="register"', false)
            ->assertSee('test-site-key')
            ->assertDontSee('test-secret-key');
        config(['turnstile.enabled' => false]);
        $this->get(route('customer.register'))->assertOk()
            ->assertDontSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false);
        Http::assertNothingSent();
    }

    public function test_registration_ip_limit_runs_before_external_verification(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('customer.register.store'), $this->data(['cf-turnstile-response' => null]))
                ->assertSessionHasErrors('cf-turnstile-response');
        }
        $this->post(route('customer.register.store'), $this->data())
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertDatabaseCount('users', 0);
        Http::assertNothingSent();
    }
}
