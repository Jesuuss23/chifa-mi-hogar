<?php

namespace Tests\Feature;

use App\Jobs\SendOrderWhatsappNotification;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class JsonCheckoutTokenTest extends TestCase
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
            'name' => 'Pruebas', 'slug' => 'pruebas', 'is_active' => true,
        ]);
        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chaufa', 'slug' => 'chaufa', 'price' => '20.00',
            'is_available' => true,
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
    public function test_quote_create_and_retry_use_one_order_and_notification(
        ?string $role,
        string $route
    ): void {
        $user = $role ? User::factory()->create(['role' => $role]) : null;
        if ($user) {
            $this->actingAs($user);
        }
        $token = $this->quoteToken();

        $response = $this->postJson(route($route), $this->payload($token))
            ->assertCreated()
            ->assertJsonPath('order.total', '40.00')
            ->assertJsonStructure(['message', 'order' => [
                'code', 'status', 'payment_status', 'subtotal', 'delivery_fee', 'total',
            ]]);

        $code = $response->json('order.code');
        $order = Order::where('code', $code)->firstOrFail();
        $this->assertSame($token, $order->checkout_token);
        $this->assertEquals($user?->id, $order->user_id);

        StoreSetting::current()->update(['accepting_orders' => false]);
        $retry = $this->payload($token);
        $retry['items'][0]['quantity'] = 3;
        $this->postJson(route($route), $retry)
            ->assertCreated()
            ->assertJsonPath('order.code', $code)
            ->assertJsonPath('order.total', '40.00');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('order_status_histories', 1);
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
    }

    public function test_quotes_reuse_unused_token_and_do_not_touch_html_checkout(): void
    {
        $htmlToken = (string) Str::uuid();
        $cart = ['plate' => ['product_id' => $this->product->id, 'quantity' => 1]];
        $this->withSession(['checkout_token' => $htmlToken, 'cart' => $cart]);

        $first = $this->quoteToken();
        $this->assertSame($first, $this->quoteToken());
        $this->assertNotSame($htmlToken, $first);
        $this->postJson(route('checkout.store'), $this->payload($first))
            ->assertCreated()
            ->assertSessionHas('checkout_token', $htmlToken)
            ->assertSessionHas('cart', $cart);
    }

    public function test_quote_after_purchase_issues_new_token_for_next_order(): void
    {
        $first = $this->quoteToken();
        $this->postJson(route('checkout.store'), $this->payload($first))->assertCreated();
        $second = $this->quoteToken();
        $this->assertNotSame($first, $second);

        $this->postJson(route('checkout.store'), $this->payload($first))->assertStatus(419);
        $this->postJson(route('checkout.store'), $this->payload($second))->assertCreated();
        $this->assertDatabaseCount('orders', 2);
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 2);
    }

    public static function invalidTokens(): array
    {
        return ['missing' => [null], 'malformed' => ['not-a-uuid']];
    }

    #[DataProvider('invalidTokens')]
    public function test_missing_or_malformed_token_is_rejected(?string $token): void
    {
        $this->postJson(route('checkout.store'), $this->payload($token))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('checkout_token');
        $this->assertDatabaseCount('orders', 0);
        Bus::assertNothingDispatched();
    }

    public function test_valid_uuid_without_session_token_is_rejected(): void
    {
        $this->postJson(route('checkout.store'), $this->payload((string) Str::uuid()))
            ->assertStatus(419);
        $this->assertDatabaseCount('orders', 0);
        Bus::assertNothingDispatched();
    }

    public function test_other_session_cannot_recover_existing_order(): void
    {
        $token = $this->quoteToken();
        $this->postJson(route('checkout.store'), $this->payload($token))->assertCreated();

        $this->flushSession();
        $this->postJson(route('checkout.store'), $this->payload($token))
            ->assertStatus(419)
            ->assertJsonMissingPath('order');
        $this->withSession(['json_checkout_token' => (string) Str::uuid()])
            ->postJson(route('checkout.store'), $this->payload($token))
            ->assertStatus(419)
            ->assertJsonMissingPath('order');

        $this->assertDatabaseCount('orders', 1);
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
    }

    public function test_html_token_does_not_authorize_json_checkout(): void
    {
        $token = (string) Str::uuid();
        $this->withSession(['checkout_token' => $token])
            ->postJson(route('checkout.store'), $this->payload($token))
            ->assertStatus(419);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_failed_quote_does_not_rotate_existing_token(): void
    {
        $token = $this->quoteToken();
        StoreSetting::current()->update(['accepting_orders' => false]);
        $this->postJson(route('checkout.quote'), $this->payload())
            ->assertUnprocessable()
            ->assertSessionHas('json_checkout_token', $token);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_server_recalculates_prices_and_failed_creation_can_be_retried(): void
    {
        $token = $this->quoteToken();
        $this->product->update(['is_available' => false]);
        $this->postJson(route('checkout.store'), $this->payload($token))
            ->assertUnprocessable()
            ->assertSessionHas('json_checkout_token', $token);
        $this->assertDatabaseCount('orders', 0);
        Bus::assertNothingDispatched();

        $this->product->update(['is_available' => true, 'price' => '25.00']);
        $payload = $this->payload($token);
        $payload['total'] = '0.01';
        $payload['items'][0]['unit_price'] = '0.01';
        $this->postJson(route('checkout.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('order.total', '50.00');
        $this->assertDatabaseCount('orders', 1);
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
    }

    public static function unauthorizedRoles(): array
    {
        return ['guest' => [null, 401], 'customer' => ['customer', 403], 'driver' => ['delivery', 403]];
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_admin_checkout_remains_restricted(?string $role, int $status): void
    {
        if ($role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
        }
        $token = $this->quoteToken();
        $this->postJson(route('admin.checkout.store'), $this->payload($token))
            ->assertStatus($status);
        $this->assertDatabaseCount('orders', 0);
        Bus::assertNothingDispatched();
    }

    private function quoteToken(): string
    {
        $response = $this->postJson(route('checkout.quote'), $this->payload())
            ->assertOk()
            ->assertJsonStructure(['checkout_token', 'message', 'quote', 'items']);
        $token = $response->json('checkout_token');
        $this->assertTrue(Str::isUuid($token));
        $response->assertSessionHas('json_checkout_token', $token);

        return $token;
    }

    private function payload(?string $token = null): array
    {
        $payload = [
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'delivery_address' => 'Direccion de prueba',
            'delivery_latitude' => -12,
            'delivery_longitude' => -77,
            'payment_method' => 'cash',
            'cash_payment_type' => 'exact',
            'items' => [[
                'type' => 'product', 'id' => $this->product->id, 'quantity' => 2,
            ]],
        ];
        if ($token !== null) {
            $payload['checkout_token'] = $token;
        }

        return $payload;
    }
}
