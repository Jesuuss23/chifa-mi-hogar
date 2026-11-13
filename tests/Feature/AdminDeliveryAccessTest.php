<?php

namespace Tests\Feature;

use App\Http\Controllers\Delivery\LocationController;
use App\Http\Controllers\Delivery\OrderController as DeliveryOrderController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class AdminDeliveryAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array']);
    }

    private function order(User $driver, array $overrides = []): Order
    {
        return Order::create(array_replace([
            'code' => 'CHF-'.Str::ulid(), 'checkout_token' => (string) Str::uuid(),
            'tracking_token' => (string) Str::uuid(), 'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999', 'delivery_type' => 'delivery',
            'delivery_user_id' => $driver->id, 'subtotal' => '20.00', 'total' => '20.00',
            'payment_method' => 'cash', 'payment_status' => 'pending', 'status' => 'preparing',
            'delivery_latitude' => -12.05, 'delivery_longitude' => -77.02,
            'driver_tracking_started_at' => now()->subMinutes(2),
            'driver_latitude' => -12, 'driver_longitude' => -77,
            'driver_location_updated_at' => now()->subMinute(),
        ], $overrides));
    }

    public function test_admin_can_select_self_without_changing_role_and_only_sees_assigned_orders(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $otherAdmin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'delivery']);
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($driver);
        $otherOrder = $this->order($driver);

        $this->actingAs($admin)->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Yo · '.$admin->name.' (administrador)')
            ->assertSee('href="'.route('delivery.orders.index').'"', false)
            ->assertViewHas('drivers', fn ($drivers) =>
                $drivers->contains('id', $admin->id) && $drivers->contains('id', $driver->id)
                && !$drivers->contains('id', $customer->id) && !$drivers->contains('id', $otherAdmin->id));
        $this->patch(route('admin.orders.assign-driver', $order), ['delivery_user_id' => $admin->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertEquals($admin->id, $order->delivery_user_id);
        foreach (['driver_latitude', 'driver_longitude', 'driver_location_updated_at',
            'driver_tracking_started_at', 'driver_tracking_stopped_at'] as $field) {
            $this->assertNull($order->{$field});
        }
        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertTrue($admin->canDeliverOrders());
        $this->assertFalse($admin->isDelivery());

        $this->get(route('delivery.orders.index'))->assertOk()->assertSee($order->code)
            ->assertDontSee($otherOrder->code)->assertSee('Volver al administrador');
        $this->get(route('delivery.orders.show', $order))->assertOk()->assertSee('Ver pedido en administración');
        $this->get(route('admin.orders.show', $order))->assertOk();
    }

    public function test_self_assignment_keeps_existing_status_and_target_validation(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'delivery']);
        $this->actingAs($admin);
        foreach (['pending', 'on_the_way', 'delivered', 'cancelled'] as $status) {
            $order = $this->order($driver, ['status' => $status]);
            $before = $order->fresh()->getAttributes();
            $this->patch(route('admin.orders.assign-driver', $order), ['delivery_user_id' => $admin->id])
                ->assertSessionHasErrors('delivery_user_id');
            $this->assertSame($before, $order->fresh()->getAttributes());
        }
        $order = $this->order($driver);
        foreach (['admin', 'customer'] as $role) {
            $other = User::factory()->create(['role' => $role]);
            $this->patch(route('admin.orders.assign-driver', $order), ['delivery_user_id' => $other->id])
                ->assertSessionHasErrors('delivery_user_id');
            $this->assertEquals($driver->id, $order->fresh()->delivery_user_id);
        }
        $order->update(['status' => 'confirmed']);
        $this->patch(route('admin.orders.assign-driver', $order), ['delivery_user_id' => $admin->id])
            ->assertSessionHasNoErrors();
        $this->patch(route('admin.orders.assign-driver', $order), ['delivery_user_id' => $driver->id])
            ->assertSessionHasNoErrors();
        $this->get(route('delivery.orders.show', $order))->assertForbidden();
    }

    public function test_admin_can_dispatch_track_collect_and_deliver_own_order_once(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order($admin, ['driver_tracking_started_at' => null]);
        $this->actingAs($admin);
        $this->patch(route('admin.orders.status', $order), ['status' => 'on_the_way'])
            ->assertSessionHasNoErrors();
        $this->postJson(route('delivery.gps.start', $order))->assertOk();
        $this->postJson(route('delivery.gps.update', $order), [
            'latitude' => -12.05, 'longitude' => -77.02, 'accuracy' => 10,
        ])->assertOk();
        $this->assertEquals(-12.05, $order->fresh()->driver_latitude);
        $this->postJson(route('delivery.gps.stop', $order))->assertOk();
        $this->assertNull($order->fresh()->driver_latitude);
        $this->postJson(route('delivery.gps.start', $order))->assertOk();

        $this->patch(route('delivery.orders.deliver', $order), ['confirm_delivery' => 1])
            ->assertSessionHasErrors('confirm_collection');
        $this->assertSame('on_the_way', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);

        $payload = ['confirm_delivery' => 1, 'confirm_collection' => 1];
        $this->patch(route('delivery.orders.deliver', $order), $payload)->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('verified', $order->payment_status);
        $this->assertEquals($admin->id, $order->payment_verified_by);
        $this->assertNotNull($order->driver_tracking_stopped_at);
        $this->assertNull($order->driver_latitude);
        $this->assertNull($order->driver_longitude);
        $this->assertNull($order->driver_location_updated_at);
        $history = $order->statusHistories()->where('to_status', 'delivered')->sole();
        $this->assertEquals($admin->id, $history->changed_by);
        $before = $order->fresh()->getAttributes();
        $this->patch(route('delivery.orders.deliver', $order), $payload)->assertSessionHasNoErrors();
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertSame(1, $order->statusHistories()->where('to_status', 'delivered')->count());
        $this->getJson(route('admin.orders.updates', ['ids' => [$order->id]]))->assertOk()
            ->assertJsonPath('orders.0.status', 'delivered');
        $this->get(route('delivery.orders.index'))->assertOk()->assertDontSee($order->code);
    }

    public function test_admin_cannot_use_delivery_actions_for_another_drivers_order(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(User::factory()->create(['role' => 'delivery']), ['status' => 'on_the_way']);
        $before = $order->fresh()->getAttributes();
        $this->actingAs($admin)->get(route('delivery.orders.show', $order))->assertForbidden();
        foreach (['start', 'update', 'stop'] as $action) {
            $this->postJson(route('delivery.gps.'.$action, $order), [
                'latitude' => -12, 'longitude' => -77, 'accuracy' => 10,
            ])->assertForbidden();
        }
        $this->patch(route('delivery.orders.deliver', $order), ['confirm_delivery' => 1, 'confirm_collection' => 1])
            ->assertForbidden();
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public static function lockedActions(): array
    {
        return [['start'], ['update'], ['stop'], ['deliver']];
    }

    #[DataProvider('lockedActions')]
    public function test_admin_assignment_is_rechecked_under_lock(string $action): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order($admin, ['status' => 'on_the_way']);
        $bound = $order->fresh();
        $order->update(['delivery_user_id' => User::factory()->create(['role' => 'delivery'])->id]);
        $before = $order->fresh()->getAttributes();
        $request = Request::create('/delivery-test', 'POST', [
            'latitude' => -12, 'longitude' => -77, 'accuracy' => 10,
            'confirm_delivery' => 1, 'confirm_collection' => 1,
        ]);
        $request->setUserResolver(fn () => $admin);
        try {
            $controller = $action === 'deliver' ? DeliveryOrderController::class : LocationController::class;
            app($controller)->{$action}($request, $bound);
            $this->fail('A reassigned order must reject the former assignee, including an administrator.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public static function loginRoles(): array
    {
        return [['admin', true], ['delivery', true], ['customer', false]];
    }

    #[DataProvider('loginRoles')]
    public function test_delivery_login_accepts_only_delivery_capable_accounts(string $role, bool $allowed): void
    {
        $user = User::factory()->create(['role' => $role, 'password' => 'Test-only-password-123']);
        $response = $this->post(route('delivery.login.submit'), [
            'email' => $user->email, 'password' => 'Test-only-password-123',
        ]);
        if ($allowed) {
            $response->assertRedirect(route('delivery.orders.index'));
            $this->assertAuthenticatedAs($user);
        } else {
            $response->assertSessionHasErrors('email');
            $this->assertGuest();
        }
        $this->assertSame($role, $user->fresh()->role);
    }

    public function test_customer_remains_blocked_even_if_assigned_and_driver_has_no_admin_access(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $order = $this->order($customer, ['status' => 'on_the_way']);
        $this->actingAs($customer)->get(route('delivery.orders.index'))->assertForbidden();
        $this->get(route('delivery.orders.show', $order))->assertForbidden();
        $this->patch(route('delivery.orders.deliver', $order), ['confirm_delivery' => 1, 'confirm_collection' => 1])
            ->assertForbidden();
        foreach (['start', 'update', 'stop'] as $action) {
            $this->postJson(route('delivery.gps.'.$action, $order))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'delivery']))
            ->get(route('admin.orders.show', $order))->assertForbidden();
    }
}
