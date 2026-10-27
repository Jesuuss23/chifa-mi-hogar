<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminOrderPaymentAndDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutExceptionHandling();
    }

    #[DataProvider('collectableStatuses')]
    public function test_admin_can_collect_cash_without_dispatch_details(string $status): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order([
            'status' => $status,
            'delivery_user_id' => null,
            'delivery_latitude' => null,
            'delivery_longitude' => null,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->patch(route('admin.orders.collect-cash', $order), [
                'confirm_collection' => '1',
            ])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('verified', $order->payment_status);
        $this->assertEquals($admin->id, $order->payment_verified_by);
        $this->assertNotNull($order->payment_verified_at);
        $this->assertSame($status, $order->status);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    public static function collectableStatuses(): array
    {
        return [
            'confirmed' => ['confirmed'],
            'preparing' => ['preparing'],
            'on the way' => ['on_the_way'],
        ];
    }

    #[DataProvider('invalidDispatchDetails')]
    public function test_dispatch_rejects_incomplete_delivery_without_mutating_order(array $overrides): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order(array_replace([
            'delivery_user_id' => $driver->id,
        ], $overrides));

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->patch(route('admin.orders.status', $order), ['status' => 'on_the_way'])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasErrors([
                'status' => 'Debes asignar un repartidor y configurar el destino antes de iniciar la entrega.',
            ]);

        $order->refresh();
        $this->assertSame('preparing', $order->status);
        $this->assertNull($order->on_the_way_at);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    public static function invalidDispatchDetails(): array
    {
        return [
            'no driver' => [['delivery_user_id' => null]],
            'no latitude' => [['delivery_latitude' => null]],
            'no longitude' => [['delivery_longitude' => null]],
            'not delivery' => [['delivery_type' => 'pickup']],
        ];
    }

    #[DataProvider('dispatchablePayments')]
    public function test_valid_dispatch_records_status_timestamp_and_history(
        string $method,
        string $paymentStatus
    ): void {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order([
            'delivery_user_id' => $driver->id,
            'payment_method' => $method,
            'payment_status' => $paymentStatus,
            // Zero is a coordinate, not a missing value.
            'delivery_latitude' => 0,
            'delivery_longitude' => 0,
        ]);

        $this->actingAs($admin)
            ->from(route('admin.orders.show', $order))
            ->patch(route('admin.orders.status', $order), ['status' => 'on_the_way'])
            ->assertRedirect(route('admin.orders.show', $order))
            ->assertSessionHasNoErrors();

        $order->refresh();
        $this->assertSame('on_the_way', $order->status);
        $this->assertSame($paymentStatus, $order->payment_status);
        $this->assertNotNull($order->on_the_way_at);
        $this->assertSame(1, $order->statusHistories()->count());
        $this->assertDatabaseHas('order_status_histories', [
            'order_id' => $order->id,
            'from_status' => 'preparing',
            'to_status' => 'on_the_way',
            'changed_by' => $admin->id,
        ]);
    }

    public static function dispatchablePayments(): array
    {
        return [
            'cash on delivery' => ['cash', 'pending'],
            'cash already paid' => ['cash', 'verified'],
            'verified Yape' => ['yape', 'verified'],
            'verified Plin' => ['plin', 'verified'],
        ];
    }

    #[DataProvider('blockedPayments')]
    public function test_dispatch_still_requires_eligible_payment(
        string $method,
        string $paymentStatus
    ): void {
        $admin = User::factory()->create(['role' => 'admin']);
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order([
            'delivery_user_id' => $driver->id,
            'payment_method' => $method,
            'payment_status' => $paymentStatus,
        ]);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'on_the_way'])
            ->assertSessionHasErrors('status');

        $order->refresh();
        $this->assertSame('preparing', $order->status);
        $this->assertNull($order->on_the_way_at);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    public static function blockedPayments(): array
    {
        return [
            'pending Yape' => ['yape', 'pending'],
            'pending Plin' => ['plin', 'pending'],
            'rejected cash' => ['cash', 'rejected'],
        ];
    }

    public function test_order_cannot_be_delivered_before_cash_is_collected(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $order = $this->order(['status' => 'on_the_way']);

        $this->actingAs($admin)
            ->patch(route('admin.orders.status', $order), ['status' => 'delivered'])
            ->assertSessionHasErrors('status');

        $order->refresh();
        $this->assertSame('on_the_way', $order->status);
        $this->assertNull($order->delivered_at);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    #[DataProvider('nonAdminRoles')]
    public function test_non_admin_cannot_collect_or_dispatch(string $role): void
    {
        $this->withExceptionHandling();
        $user = User::factory()->create(['role' => $role]);
        $order = $this->order();

        $this->actingAs($user)
            ->patch(route('admin.orders.collect-cash', $order), [
                'confirm_collection' => '1',
            ])
            ->assertForbidden();

        $this->patch(route('admin.orders.status', $order), ['status' => 'on_the_way'])
            ->assertForbidden();

        $order->refresh();
        $this->assertSame('pending', $order->payment_status);
        $this->assertSame('preparing', $order->status);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    public static function nonAdminRoles(): array
    {
        return [
            'customer' => ['customer'],
            'delivery' => ['delivery'],
        ];
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_replace([
            'code' => 'CHF-'.Str::ulid(),
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'delivery_address' => 'Direccion de prueba',
            'delivery_latitude' => -12.0,
            'delivery_longitude' => -77.0,
            'subtotal' => '20.00',
            'total' => '20.00',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'preparing',
        ], $overrides));
    }
}
