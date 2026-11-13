<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Services\LoginAttemptLimiter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class AdminMobileApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['cache.default' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin', 'password' => 'Mobile-test-123!']);
    }

    private function token(User $user, array $abilities = ['admin-mobile:read'], $expires = null): string
    {
        return $user->createToken('admin-mobile:test', $abilities, $expires ?? now()->addDays(30))->plainTextToken;
    }

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();
        return $this->withHeaders(['Authorization' => 'Bearer '.$token]);
    }

    private function order(string $status = 'pending'): Order
    {
        return Order::create([
            'code' => 'CHF-'.Str::ulid(), 'checkout_token' => (string) Str::uuid(),
            'tracking_token' => (string) Str::uuid(),
            'customer_name' => 'Cliente histórico', 'customer_phone' => '999999999',
            'delivery_type' => 'delivery', 'subtotal' => 20, 'total' => 20,
            'payment_method' => 'cash', 'payment_status' => 'pending', 'status' => $status,
        ]);
    }

    public function test_login_issues_hashed_scoped_expiring_token_without_web_session(): void
    {
        $admin = $this->admin();
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email, 'password' => 'Mobile-test-123!', 'device_name' => 'Caja',
        ])->assertOk()->assertJsonPath('user.id', $admin->id)->assertJsonPath('token_type', 'Bearer');
        $plain = $response->json('access_token');
        $token = PersonalAccessToken::findToken($plain);
        $this->assertSame(['admin-mobile:read'], $token->abilities);
        $this->assertTrue($token->expires_at->isFuture());
        $this->assertNotSame($plain, $token->token);
        $this->assertGuest('web');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $this->bearer($plain)->getJson('/api/v1/admin/auth/me')->assertOk()->assertJsonPath('data.role', 'admin');
    }

    public function test_customer_and_delivery_cannot_login_or_read_orders(): void
    {
        foreach (['customer', 'delivery'] as $role) {
            $user = User::factory()->create(['role' => $role, 'password' => 'Mobile-test-123!']);
            $this->postJson('/api/v1/admin/auth/login', [
                'email' => $user->email, 'password' => 'Mobile-test-123!', 'device_name' => 'Phone',
            ])->assertUnprocessable();
            $this->assertSame(0, $user->tokens()->count());
            $this->bearer($this->token($user))->getJson('/api/v1/admin/orders')->assertForbidden();
        }
    }

    public function test_unauthenticated_requests_are_json_and_not_cacheable_even_without_accept_header(): void
    {
        $response = $this->get('/api/v1/admin/orders')->assertUnauthorized();
        $this->assertStringContainsString('application/json', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_web_session_cannot_replace_mobile_token(): void
    {
        $this->actingAs($this->admin())->getJson('/api/v1/admin/orders')->assertUnauthorized();
    }

    public function test_expired_and_wrong_ability_tokens_are_rejected(): void
    {
        $admin = $this->admin();
        $this->bearer($this->token($admin, ['admin-mobile:read'], now()->subMinute()))
            ->getJson('/api/v1/admin/orders')->assertUnauthorized();
        $this->bearer($this->token($admin, ['unrelated']))
            ->getJson('/api/v1/admin/orders')->assertForbidden();
    }

    public function test_logout_revokes_only_current_token(): void
    {
        $admin = $this->admin();
        $first = $this->token($admin);
        $second = $this->token($admin);
        $this->bearer($first)->postJson('/api/v1/admin/auth/logout')->assertNoContent();
        $this->bearer($first)->getJson('/api/v1/admin/auth/me')->assertUnauthorized();
        $this->bearer($second)->getJson('/api/v1/admin/auth/me')->assertOk();
    }

    public function test_password_and_role_changes_revoke_mobile_tokens(): void
    {
        $admin = $this->admin();
        $first = $this->token($admin);
        $admin->forceFill(['password' => 'Another-test-123!'])->save();
        $this->bearer($first)->getJson('/api/v1/admin/orders')->assertUnauthorized();
        $second = $this->token($admin);
        $admin->forceFill(['role' => 'customer'])->save();
        $this->bearer($second)->getJson('/api/v1/admin/orders')->assertUnauthorized();
    }

    public function test_current_database_role_is_checked_even_without_model_events(): void
    {
        $admin = $this->admin();
        $token = $this->token($admin);
        User::whereKey($admin->id)->update(['role' => 'customer']);
        $this->bearer($token)->getJson('/api/v1/admin/orders')->assertForbidden();
    }

    public function test_list_filters_and_paginates_without_exposing_private_tokens(): void
    {
        $wanted = $this->order('preparing');
        $this->order('delivered');
        $response = $this->bearer($this->token($this->admin()))
            ->getJson('/api/v1/admin/orders?status=preparing&per_page=1&search='.$wanted->code)
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.id', $wanted->id)->assertJsonPath('data.0.total', '20.00');
        $this->assertArrayNotHasKey('tracking_token', $response->json('data.0'));
        $this->assertArrayNotHasKey('checkout_token', $response->json('data.0'));
        $this->assertArrayNotHasKey('customer_phone', $response->json('data.0'));
    }

    public function test_detail_uses_historical_item_and_combo_snapshots(): void
    {
        $order = $this->order();
        $item = $order->items()->create([
            'item_type' => 'combo', 'catalog_item_id' => 999999,
            'item_name' => 'Combo histórico', 'quantity' => 2,
            'unit_price' => 10, 'extras_unit_total' => 0, 'line_total' => 20,
        ]);
        $item->comboComponents()->create([
            'component_type' => 'product', 'catalog_item_id' => 999999,
            'component_name' => 'Chaufa histórico', 'included_quantity' => 1,
            'selected_quantity' => 1, 'additional_quantity' => 0,
            'unit_price' => 10, 'additional_total' => 0,
        ]);
        $before = $order->fresh()->getAttributes();
        $response = $this->bearer($this->token($this->admin()))
            ->getJson('/api/v1/admin/orders/'.$order->id)->assertOk()
            ->assertJsonPath('data.items.0.name', 'Combo histórico')
            ->assertJsonPath('data.items.0.combo_components.0.name', 'Chaufa histórico')
            ->assertJsonPath('data.customer.phone', '999999999');
        $this->assertArrayNotHasKey('tracking_token', $response->json('data'));
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_invalid_filters_and_missing_order_are_json_errors(): void
    {
        $token = $this->token($this->admin());
        foreach (['per_page=51', 'per_page=0', 'page=0', 'status=unknown', 'search[]=x'] as $query) {
            $this->bearer($token)->getJson('/api/v1/admin/orders?'.$query)->assertUnprocessable();
        }
        $this->bearer($token)->getJson('/api/v1/admin/orders/999999')->assertNotFound();
    }

    public function test_login_respects_shared_web_failure_limit(): void
    {
        $admin = $this->admin();
        $request = Request::create('/', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $limiter = app(LoginAttemptLimiter::class);
        for ($i = 0; $i < 5; $i++) {
            $limiter->failed($request, $admin->email);
        }
        $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email, 'password' => 'Mobile-test-123!', 'device_name' => 'Phone',
        ])->assertUnprocessable();
        $this->assertSame(0, $admin->tokens()->count());
    }

    public function test_sixth_login_request_from_same_ip_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/admin/auth/login', [
                'email' => "missing{$i}@example.test", 'password' => 'wrong', 'device_name' => 'Phone',
            ])->assertUnprocessable();
        }
        $response = $this->postJson('/api/v1/admin/auth/login', [
            'email' => 'other@example.test', 'password' => 'wrong', 'device_name' => 'Phone',
        ])->assertStatus(429);
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_eleventh_session_retires_oldest_mobile_token_only(): void
    {
        $admin = $this->admin();
        $other = $admin->createToken('other-integration', ['other'])->plainTextToken;
        $oldest = $this->token($admin);
        for ($i = 0; $i < 9; $i++) {
            $this->token($admin);
        }
        $this->postJson('/api/v1/admin/auth/login', [
            'email' => $admin->email, 'password' => 'Mobile-test-123!', 'device_name' => 'New phone',
        ])->assertOk();
        $this->assertNull(PersonalAccessToken::findToken($oldest));
        $this->assertNotNull(PersonalAccessToken::findToken($other));
        $this->assertSame(10, $admin->tokens()->where('name', 'like', 'admin-mobile:%')->count());
    }
}
