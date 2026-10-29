<?php

namespace Tests\Feature;

use App\Http\Controllers\Delivery\LocationController;
use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Tests\TestCase;

class DeliveryGpsAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    public static function staleOrders(): array
    {
        $cases = [];
        foreach (['start', 'update', 'stop'] as $action) {
            foreach (['reassigned', 'unassigned', 'not delivery'] as $change) {
                $cases[$action.' / '.$change] = [$action, $change];
            }
        }

        return $cases;
    }

    #[DataProvider('staleOrders')]
    public function test_gps_reauthorizes_the_locked_order(string $action, string $change): void
    {
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order($driver);
        // Retain the model initially authorized by route binding.
        $boundOrder = $order->fresh();
        $changes = match ($change) {
            'reassigned' => ['delivery_user_id' => User::factory()->create(['role' => 'delivery'])->id],
            'unassigned' => ['delivery_user_id' => null],
            'not delivery' => ['delivery_type' => 'pickup'],
        };
        Order::whereKey($order->id)->update($changes);
        $before = $order->fresh()->getAttributes();

        $request = Request::create('/gps', 'POST', $this->coordinates());
        $request->setUserResolver(fn () => $driver);

        try {
            app(LocationController::class)->{$action}($request, $boundOrder);
            $this->fail('A stale assignment was authorized.');
        } catch (HttpExceptionInterface $exception) {
            $this->assertSame($change === 'not delivery' ? 422 : 403, $exception->getStatusCode());
        }

        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public static function actions(): array
    {
        return [['start'], ['update'], ['stop']];
    }

    #[DataProvider('actions')]
    public function test_another_driver_cannot_access_gps_endpoints(string $action): void
    {
        $owner = User::factory()->create(['role' => 'delivery']);
        $other = User::factory()->create(['role' => 'delivery']);
        $order = $this->order($owner);
        $before = $order->fresh()->getAttributes();

        $this->actingAs($other)->postJson(route('delivery.gps.'.$action, $order), $this->coordinates())
            ->assertForbidden();
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    public function test_assigned_driver_can_start_update_and_stop_tracking(): void
    {
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order($driver);
        $order->update([
            'driver_tracking_started_at' => null,
            'driver_tracking_stopped_at' => null,
        ]);
        $this->actingAs($driver);

        $this->postJson(route('delivery.gps.start', $order))->assertOk();
        $order->refresh();
        $this->assertNotNull($order->driver_tracking_started_at);
        $this->assertNull($order->driver_latitude);
        $this->assertNull($order->driver_longitude);
        $this->assertNull($order->driver_location_updated_at);

        $this->postJson(route('delivery.gps.update', $order), $this->coordinates())->assertOk();
        $order->refresh();
        $this->assertEquals(-12.05, $order->driver_latitude);
        $this->assertEquals(-77.02, $order->driver_longitude);
        $this->assertNotNull($order->driver_location_updated_at);

        $this->postJson(route('delivery.gps.stop', $order))->assertOk();
        $order->refresh();
        $this->assertNotNull($order->driver_tracking_stopped_at);
        $this->assertNull($order->driver_latitude);
        $this->assertNull($order->driver_longitude);
        $this->assertNull($order->driver_location_updated_at);
    }

    public static function finalStates(): array
    {
        return [['delivered'], ['cancelled']];
    }

    #[DataProvider('finalStates')]
    public function test_final_order_cannot_receive_a_location(string $status): void
    {
        $driver = User::factory()->create(['role' => 'delivery']);
        $order = $this->order($driver);
        $order->update(['status' => $status]);
        $before = $order->fresh()->getAttributes();

        $this->actingAs($driver)->postJson(route('delivery.gps.update', $order), $this->coordinates())
            ->assertUnprocessable();
        $this->assertSame($before, $order->fresh()->getAttributes());
    }

    private function coordinates(): array
    {
        return ['latitude' => -12.05, 'longitude' => -77.02, 'accuracy' => 10];
    }

    private function order(User $driver): Order
    {
        return Order::create([
            'code' => 'CHF-'.Str::ulid(),
            'checkout_token' => (string) Str::uuid(),
            'tracking_token' => (string) Str::uuid(),
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'delivery_user_id' => $driver->id,
            'subtotal' => '20.00',
            'total' => '20.00',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'on_the_way',
            'driver_tracking_started_at' => now()->subMinutes(2),
            'driver_latitude' => -12,
            'driver_longitude' => -77,
            'driver_location_updated_at' => now()->subMinute(),
        ]);
    }
}
