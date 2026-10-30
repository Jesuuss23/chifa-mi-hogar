<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\StoreSetting;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'app.timezone' => 'UTC']);
        $this->withoutVite();
        $this->travelTo(CarbonImmutable::parse('2026-10-30 07:00:00', 'UTC'));
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-10-30 07:00:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_empty_dashboard_uses_real_kitchen_state(): void
    {
        StoreSetting::current()->update(['accepting_orders' => true]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))
            ->assertOk()
            ->assertViewHas('ordersToday', 0)
            ->assertViewHas('salesToday', fn ($value) => (float) $value === 0.0)
            ->assertViewHas('pendingOrders', 0)
            ->assertViewHas('kitchenOpen', true)
            ->assertViewHas('recentOrders', fn ($orders) => $orders->isEmpty())
            ->assertSee('Todavía no hay pedidos registrados.');

        StoreSetting::current()->update(['accepting_orders' => false]);
        $this->get(route('admin.dashboard'))->assertOk()->assertViewHas('kitchenOpen', false);
    }

    public function test_today_uses_lima_midnight_and_excludes_next_day(): void
    {
        foreach (['2026-10-30 04:59:59', '2026-10-30 05:00:00',
                  '2026-10-31 04:59:59', '2026-10-31 05:00:00'] as $date) {
            $this->order(['created_at' => $date, 'status' => 'cancelled']);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))->assertOk()->assertViewHas('ordersToday', 2);
    }

    public function test_sales_count_delivery_date_verified_payments_and_total(): void
    {
        $base = ['status' => 'delivered', 'payment_status' => 'verified',
                 'created_at' => '2026-10-29 12:00:00', 'total' => '25.50'];
        $this->order(array_replace($base, ['delivered_at' => '2026-10-30 05:00:00']));
        $this->order(array_replace($base, ['delivered_at' => '2026-10-31 04:59:59', 'total' => '10.25']));
        $this->order(array_replace($base, ['delivered_at' => '2026-10-30 04:59:59']));
        $this->order(array_replace($base, ['delivered_at' => '2026-10-31 05:00:00']));
        $this->order(array_replace($base, ['delivered_at' => null]));
        foreach (['pending', 'rejected'] as $payment) {
            $this->order(array_replace($base, ['delivered_at' => '2026-10-30 06:00:00', 'payment_status' => $payment]));
        }
        foreach (['pending', 'confirmed', 'preparing', 'on_the_way', 'cancelled'] as $status) {
            $this->order(array_replace($base, ['delivered_at' => '2026-10-30 06:00:00', 'status' => $status]));
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('salesToday', fn ($value) => (float) $value === 35.75)
            ->assertSee('S/ 35.75');
    }

    public function test_pending_count_includes_older_orders_but_not_other_states(): void
    {
        $this->order(['status' => 'pending', 'created_at' => '2026-10-01 12:00:00']);
        $this->order(['status' => 'pending']);
        foreach (['confirmed', 'preparing', 'on_the_way', 'delivered', 'cancelled'] as $status) {
            $this->order(['status' => $status]);
        }

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))->assertOk()->assertViewHas('pendingOrders', 2);
    }

    public function test_recent_orders_are_limited_sorted_linked_and_escaped(): void
    {
        $ids = [];
        for ($i = 0; $i < 7; $i++) {
            $ids[] = $this->order([
                'customer_name' => '<script>alert(1)</script>',
                'created_at' => '2026-10-30 06:00:00',
            ])->id;
        }
        $older = $this->order(['created_at' => '2026-10-29 06:00:00']);
        $expected = array_reverse(array_slice($ids, -5));

        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.dashboard'))->assertOk()
            ->assertViewHas('recentOrders', fn ($orders) => $orders->pluck('id')->all() === $expected)
            ->assertSee(route('admin.orders.show', $expected[0]), false)
            ->assertDontSee($older->code)
            ->assertSee('30/10/2026 01:00')
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('noindex, nofollow');
    }

    #[DataProvider('nonAdminRoles')]
    public function test_other_roles_cannot_read_dashboard(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('admin.dashboard'))->assertForbidden();
    }

    public static function nonAdminRoles(): array
    {
        return [['customer'], ['delivery']];
    }

    public function test_guest_cannot_read_dashboard(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect();
    }

    private function order(array $overrides = []): Order
    {
        $order = new Order();
        // created_at is intentionally not fillable in the production model.
        $order->forceFill(array_replace([
            'code' => 'CHF-'.Str::ulid(),
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'delivery_address' => 'Dirección de prueba',
            'subtotal' => '20.00',
            'total' => '20.00',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'pending',
            'created_at' => '2026-10-30 06:00:00',
        ], $overrides))->save();

        return $order;
    }
}
