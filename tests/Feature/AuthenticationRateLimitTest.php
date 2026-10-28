<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationRateLimitTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Cache::flush();
        $this->freezeTime();
    }

    public static function portals(): array
    {
        return [
            'customer' => ['customer', 'customer.login.store', 'customer.account', 'customer.logout'],
            'admin' => ['admin', 'admin.login.submit', 'admin.dashboard', 'admin.logout'],
            'delivery' => ['delivery', 'delivery.login.submit', 'delivery.orders.index', 'delivery.logout'],
        ];
    }

    #[DataProvider('portals')]
    public function test_five_failures_block_correct_password_until_window_expires(
        string $role, string $login, string $destination, string $logout
    ): void {
        $user = User::factory()->create(['role' => $role]);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route($login), ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }

        // El límite por IP de un minuto ya venció; sigue el de correo + IP.
        $this->travel(61)->seconds();
        $this->post(route($login), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->travel(840)->seconds();
        $this->post(route($login), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route($destination));
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('portals')]
    public function test_success_resets_failures(
        string $role, string $login, string $destination, string $logout
    ): void {
        $user = User::factory()->create(['role' => $role]);
        for ($i = 0; $i < 4; $i++) {
            $this->post(route($login), ['email' => $user->email, 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }
        $this->post(route($login), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route($destination));
        $this->post(route($logout))->assertRedirect();
        $this->travel(61)->seconds();
        $this->post(route($login), ['email' => $user->email, 'password' => 'wrong'])
            ->assertSessionHasErrors('email');
        $this->post(route($login), ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect(route($destination));
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('portals')]
    public function test_ip_limit_blocks_rotating_email_addresses(
        string $role, string $login, string $destination, string $logout
    ): void {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route($login), ['email' => "unknown{$i}@example.com", 'password' => 'wrong'])
                ->assertSessionHasErrors('email');
        }
        $this->post(route($login), ['email' => 'another@example.com', 'password' => 'wrong'])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->assertGuest();
    }

    public function test_changing_portal_or_email_case_does_not_reset_failures(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'email' => 'admin@example.com']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('customer.login.store'), [
                'email' => 'ADMIN@example.com', 'password' => 'wrong',
            ])->assertSessionHasErrors('email');
        }
        $this->travel(61)->seconds();
        $this->post(route('admin.login.submit'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_failures_from_one_ip_do_not_lock_other_ips(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.1']);
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('customer.login.store'), [
                'email' => $user->email, 'password' => 'wrong',
            ])->assertSessionHasErrors('email');
        }
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.2']);
        $this->post(route('customer.login.store'), [
            'email' => $user->email, 'password' => 'password',
        ])->assertRedirect(route('customer.account'));
        $this->assertAuthenticatedAs($user);
    }

    #[DataProvider('portals')]
    public function test_wrong_role_still_cannot_log_in(
        string $role, string $login, string $destination, string $logout
    ): void {
        $user = User::factory()->create(['role' => $role === 'customer' ? 'admin' : 'customer']);
        $this->post(route($login), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
    }
}
