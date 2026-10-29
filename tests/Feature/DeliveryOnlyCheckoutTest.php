<?php

namespace Tests\Feature;

use App\Jobs\SendOrderWhatsappNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\DeliveryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DeliveryOnlyCheckoutTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Bus::fake([SendOrderWhatsappNotification::class]);
        StoreSetting::current()->update([
            'accepting_orders' => true,
            'kitchen_latitude' => -12,
            'kitchen_longitude' => -77,
        ]);
        $category = Category::create([
            'name' => 'Carta', 'slug' => 'carta', 'is_active' => true,
        ]);
        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chaufa', 'slug' => 'chaufa',
            'price' => '20.00', 'is_available' => true,
        ]);
    }

    public static function buyers(): array
    {
        return [
            'guest' => [null, 'checkout.store'],
            'customer' => ['customer', 'checkout.store'],
            'admin' => ['admin', 'admin.checkout.store'],
        ];
    }

    #[DataProvider('buyers')]
    public function test_pickup_cannot_be_quoted_or_created(?string $role, string $route): void
    {
        if ($role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
        }
        $this->mock(DeliveryService::class)->shouldNotReceive('calculate');
        $token = (string) Str::uuid();
        $payload = $this->payload($token);
        $payload['delivery_type'] = 'pickup';
        unset($payload['delivery_address'], $payload['delivery_latitude'], $payload['delivery_longitude']);
        $cart = ['keep' => ['product_id' => $this->product->id, 'quantity' => 1]];

        $this->withSession(['json_checkout_token' => $token, 'cart' => $cart])
            ->postJson(route('checkout.quote'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_type')
            ->assertSessionHas('json_checkout_token', $token)
            ->assertSessionHas('cart', $cart);

        $this->postJson(route($route), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('delivery_type')
            ->assertSessionHas('json_checkout_token', $token)
            ->assertSessionHas('cart', $cart);
        $this->assertNoOrder();
    }

    public static function serviceMethods(): array
    {
        return [['quote'], ['create']];
    }

    #[DataProvider('serviceMethods')]
    public function test_internal_calls_also_reject_pickup(string $method): void
    {
        $this->mock(DeliveryService::class)->shouldNotReceive('calculate');
        $payload = $this->payload((string) Str::uuid());
        $payload['delivery_type'] = 'pickup';

        try {
            app(OrderService::class)->{$method}($payload);
            $this->fail('Pickup was accepted by '.$method);
        } catch (ValidationException $exception) {
            $this->assertSame(
                ['Solo atendemos pedidos por delivery.'],
                $exception->errors()['delivery_type']
            );
        }
        $this->assertNoOrder();
    }

    public static function incompleteLocations(): array
    {
        return [
            'quote without latitude' => ['checkout.quote', 'delivery_latitude'],
            'quote without longitude' => ['checkout.quote', 'delivery_longitude'],
            'create without latitude' => ['checkout.store', 'delivery_latitude'],
            'create without longitude' => ['checkout.store', 'delivery_longitude'],
            'create without address' => ['checkout.store', 'delivery_address'],
        ];
    }

    #[DataProvider('incompleteLocations')]
    public function test_delivery_still_requires_location(string $route, string $field): void
    {
        $this->mock(DeliveryService::class)->shouldNotReceive('calculate');
        $token = (string) Str::uuid();
        $payload = $this->payload($token);
        unset($payload[$field]);

        $this->withSession(['json_checkout_token' => $token])
            ->postJson(route($route), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($field);
        $this->assertNoOrder();
    }

    public function test_delivery_keeps_server_fee_and_idempotent_retry(): void
    {
        $this->mock(DeliveryService::class)
            ->shouldReceive('calculate')->twice()
            ->withArgs(fn (StoreSetting $settings, float $lat, float $lng) =>
                $settings->id === 1 && $lat === -12.05 && $lng === -77.0
            )
            ->andReturn(['fee_cents' => 600, 'distance_km' => 5.56]);

        $payload = $this->payload();
        // Client-supplied totals must not bypass the delivery charge.
        $payload['delivery_fee'] = '0.00';
        $payload['total'] = '0.01';
        $quote = $this->postJson(route('checkout.quote'), $payload)
            ->assertOk()
            ->assertJsonPath('quote.delivery_fee', '6.00')
            ->assertJsonPath('quote.total', '26.00');
        $payload['checkout_token'] = $quote->json('checkout_token');

        $response = $this->postJson(route('checkout.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('order.delivery_fee', '6.00')
            ->assertJsonPath('order.total', '26.00');
        $code = $response->json('order.code');
        $order = Order::firstOrFail();
        $this->assertSame('delivery', $order->delivery_type);
        $this->assertSame('Direccion de prueba', $order->delivery_address);
        $this->assertEquals(-12.05, $order->delivery_latitude);

        StoreSetting::current()->update(['accepting_orders' => false]);
        $this->postJson(route('checkout.store'), $payload)
            ->assertCreated()->assertJsonPath('order.code', $code);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('order_status_histories', 1);
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
    }

    public function test_service_preserves_an_existing_legacy_order_without_recreating_it(): void
    {
        $token = (string) Str::uuid();
        $order = Order::create([
            'checkout_token' => $token,
            'code' => 'CHF-'.Str::ulid(),
            'customer_name' => 'Cliente anterior',
            'customer_phone' => '999999999',
            'delivery_type' => 'pickup',
            'subtotal' => '20.00',
            'total' => '20.00',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'pending',
        ]);
        $this->mock(DeliveryService::class)->shouldNotReceive('calculate');
        StoreSetting::current()->update(['accepting_orders' => false]);

        $recovered = app(OrderService::class)->create(['checkout_token' => $token]);
        $this->assertTrue($order->is($recovered));
        $this->assertSame('pickup', $order->fresh()->delivery_type);
        $this->assertSame('20.00', $order->fresh()->total);
        $this->assertDatabaseCount('orders', 1);
        Bus::assertNothingDispatched();
    }

    private function assertNoOrder(): void
    {
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        Bus::assertNothingDispatched();
    }

    private function payload(?string $token = null): array
    {
        return array_filter([
            'checkout_token' => $token,
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'delivery_address' => 'Direccion de prueba',
            'delivery_latitude' => -12.05,
            'delivery_longitude' => -77,
            'payment_method' => 'cash',
            'cash_payment_type' => 'exact',
            'items' => [['type' => 'product', 'id' => $this->product->id, 'quantity' => 1]],
        ], fn ($value) => $value !== null);
    }
}
