<?php

namespace Tests\Feature;

use App\Http\Controllers\Delivery\OrderController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class DeliveryCompletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    private function order(User $driver, array $overrides = []): Order
    {
        return Order::create(array_replace([
            'code' => 'CHF-'.Str::ulid(), 'checkout_token' => (string) Str::uuid(),
            'tracking_token' => (string) Str::uuid(), 'customer_name' => 'Cliente prueba',
            'customer_phone' => '999999999', 'delivery_type' => 'delivery',
            'delivery_user_id' => $driver->id, 'subtotal' => '20.00', 'total' => '20.00',
            'payment_method' => 'cash', 'payment_status' => 'pending', 'status' => 'on_the_way',
            'driver_tracking_started_at' => now()->subMinutes(2),
            'driver_latitude' => -12, 'driver_longitude' => -77,
            'driver_location_updated_at' => now(),
        ], $overrides));
    }

    public function test_driver_can_collect_cash_and_deliver_once_with_admin_visibility(): void
    {
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order($driver);
        $payload = ['confirm_delivery' => 1, 'confirm_collection' => 1];
        $this->actingAs($driver)->get(route('delivery.orders.show', $order))->assertOk()
            ->assertSee('Marcar pedido entregado');
        $this->patch(route('delivery.orders.deliver', $order), $payload)
            ->assertRedirect(route('delivery.orders.show', $order))->assertSessionHasNoErrors();
        $order->refresh();
        $this->assertSame('delivered', $order->status);
        $this->assertSame('verified', $order->payment_status);
        $this->assertEquals($driver->id, $order->payment_verified_by);
        $this->assertNotNull($order->payment_verified_at);
        $this->assertNotNull($order->delivered_at);
        $this->assertNotNull($order->driver_tracking_stopped_at);
        $this->assertNull($order->driver_latitude);
        $this->assertNull($order->driver_longitude);
        $this->assertNull($order->driver_location_updated_at);
        $history = $order->statusHistories()->sole();
        $this->assertSame('on_the_way', $history->from_status);
        $this->assertSame('delivered', $history->to_status);
        $this->assertEquals($driver->id, $history->changed_by);
        $before = $order->fresh()->getAttributes();
        $this->patch(route('delivery.orders.deliver', $order), $payload)->assertSessionHasNoErrors();
        $this->assertSame($before, $order->fresh()->getAttributes());
        $this->assertSame(1, $order->statusHistories()->count());
        $this->get(route('delivery.orders.show', $order))->assertOk()->assertDontSee('Marcar pedido entregado');
        $this->get(route('delivery.orders.index'))->assertOk()->assertDontSee($order->code);
        $this->postJson(route('delivery.gps.update', $order), [
            'latitude' => -12, 'longitude' => -77, 'accuracy' => 10,
        ])->assertUnprocessable();

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Entregado')
            ->assertSee('Entrega confirmada por el repartidor.');
        $this->get(route('admin.orders.index', ['status' => 'delivered']))->assertOk()->assertSee($order->code);
        $this->getJson(route('admin.orders.updates', ['ids' => [$order->id]]))->assertOk()
            ->assertJsonPath('orders.0.status', 'delivered')->assertJsonPath('orders.0.payment_status', 'verified')
            ->assertJsonMissingPath('orders.0.customer_phone')->assertJsonMissingPath('orders.0.tracking_token');
    }

    public static function verifiedMethods(): array
    {
        return [['cash'], ['yape'], ['plin']];
    }

    #[DataProvider('verifiedMethods')]
    public function test_verified_payment_is_preserved(string $method): void
    {
        $driver = User::factory()->create(['role' => 'delivery']);
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order($driver, [
            'payment_method' => $method, 'payment_status' => 'verified',
            'payment_verified_by' => $admin->id, 'payment_verified_at' => now()->subHour(),
        ]);
        $verifiedAt = $order->payment_verified_at->toDateTimeString();
        $this->actingAs($driver)->patch(route('delivery.orders.deliver', $order), ['confirm_delivery' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame('delivered', $order->fresh()->status);
        $this->assertEquals($admin->id, $order->fresh()->payment_verified_by);
        $this->assertSame($verifiedAt, $order->fresh()->payment_verified_at->toDateTimeString());
    }

    public static function invalidCompletions(): array
    {
        return [
            'no delivery confirmation' => ['on_the_way', 'cash', 'pending', [], 'confirm_delivery'],
            'no collection' => ['on_the_way', 'cash', 'pending', ['confirm_delivery' => 1], 'confirm_collection'],
            'unverified yape' => ['on_the_way', 'yape', 'pending', ['confirm_delivery' => 1, 'confirm_collection' => 1], 'payment'],
            'unverified plin' => ['on_the_way', 'plin', 'pending', ['confirm_delivery' => 1], 'payment'],
            'rejected cash' => ['on_the_way', 'cash', 'rejected', ['confirm_delivery' => 1, 'confirm_collection' => 1], 'payment'],
            'pending order' => ['pending', 'cash', 'pending', ['confirm_delivery' => 1, 'confirm_collection' => 1], 'delivery'],
            'confirmed order' => ['confirmed', 'cash', 'pending', ['confirm_delivery' => 1, 'confirm_collection' => 1], 'delivery'],
            'preparing order' => ['preparing', 'cash', 'pending', ['confirm_delivery' => 1, 'confirm_collection' => 1], 'delivery'],
            'cancelled order' => ['cancelled', 'cash', 'pending', ['confirm_delivery' => 1, 'confirm_collection' => 1], 'delivery'],
        ];
    }

    #[DataProvider('invalidCompletions')]
    public function test_invalid_completion_never_mutates_order(string $status, string $method, string $payment, array $payload, string $error): void
    {
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order($driver, ['status' => $status, 'payment_method' => $method, 'payment_status' => $payment]);
        $before = $order->fresh()->getAttributes();
        $this->actingAs($driver)->patch(route('delivery.orders.deliver', $order), $payload)->assertSessionHasErrors($error);
        $this->assertEquals($before, $order->fresh()->getAttributes());
        $this->assertSame(0, $order->statusHistories()->count());
    }

    public static function forbiddenRoles(): array
    {
        return [['delivery'], ['customer'], ['admin']];
    }

    #[DataProvider('forbiddenRoles')]
    public function test_other_users_cannot_deliver(string $role): void
    {
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order($driver);
        $before = $order->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->patch(route('delivery.orders.deliver', $order), ['confirm_delivery' => 1, 'confirm_collection' => 1])
            ->assertForbidden();
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_assignment_is_rechecked_under_lock(): void
    {
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order($driver);
        $bound = $order->fresh();
        $order->update(['delivery_user_id' => User::factory()->create(['role' => 'delivery'])->id]);
        $request = Request::create('/entregar', 'PATCH', ['confirm_delivery' => 1, 'confirm_collection' => 1]);
        $request->setUserResolver(fn () => $driver);
        try {
            app(OrderController::class)->deliver($request, $bound);
            $this->fail('The former driver must not deliver a reassigned order.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }
        $this->assertSame('on_the_way', $order->fresh()->status);
        $this->assertSame('pending', $order->fresh()->payment_status);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    public function test_non_delivery_order_is_rejected(): void
    {
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order($driver, ['delivery_type' => 'pickup']);
        $this->actingAs($driver)->patch(route('delivery.orders.deliver', $order), ['confirm_delivery' => 1])
            ->assertUnprocessable();
        $this->assertSame('on_the_way', $order->fresh()->status);
    }

    public function test_updates_are_admin_only_and_validate_bounded_ids(): void
    {
        foreach (['customer', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson(route('admin.orders.updates', ['ids' => [1]]))->assertForbidden();
        }
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->getJson(route('admin.orders.updates', ['ids' => range(1, 51)]))->assertUnprocessable();
        $this->getJson(route('admin.orders.updates', ['ids' => ['invalid']]))->assertUnprocessable();
    }
}
