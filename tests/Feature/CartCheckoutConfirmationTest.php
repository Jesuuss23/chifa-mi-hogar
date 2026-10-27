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

class CartCheckoutConfirmationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        $this->withoutExceptionHandling();
        Bus::fake([SendOrderWhatsappNotification::class]);
    }

    public static function customerTypes(): array
    {
        return ['guest' => [false], 'registered customer' => [true]];
    }

    #[DataProvider('customerTypes')]
    public function test_existing_order_returns_confirmation_without_reprocessing(bool $registered): void
    {
        $user = $registered ? User::factory()->create(['role' => 'customer']) : null;
        if ($user) {
            $this->actingAs($user);
        }

        $order = $this->order(['user_id' => $user?->id]);
        StoreSetting::current()->update(['accepting_orders' => false]);

        // A committed order remains valid even with an empty cart, no form
        // fields and a store that has since stopped accepting new orders.
        $this->withSession([
            'checkout_token' => $order->checkout_token,
            'cart' => [],
        ])->post(route('cart.checkout.confirm'), [
            'checkout_token' => $order->checkout_token,
        ])->assertRedirect(route('cart.checkout.success'))
            ->assertSessionHasNoErrors()
            ->assertSessionHas('last_confirmed_order_id', $order->id)
            ->assertSessionMissing('cart')
            ->assertSessionMissing('checkout_token');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertSame('pending', $order->fresh()->status);
        Bus::assertNothingDispatched();

        $this->get(route('cart.checkout.success'))
            ->assertOk()
            ->assertViewHas('order', fn (Order $shown) => $shown->is($order));
    }

    public static function invalidSessions(): array
    {
        return ['missing session token' => [null], 'different session token' => ['different']];
    }

    #[DataProvider('invalidSessions')]
    public function test_existing_order_cannot_be_recovered_without_matching_session(?string $session): void
    {
        $this->withExceptionHandling();
        $order = $this->order();
        $cart = ['keep' => ['product_id' => 123, 'quantity' => 1]];

        $this->withSession([
            'cart' => $cart,
            'checkout_token' => $session === null ? null : (string) Str::uuid(),
        ])->post(route('cart.checkout.confirm'), [
            'checkout_token' => $order->checkout_token,
        ])->assertStatus(419)
            ->assertSessionMissing('last_confirmed_order_id')
            ->assertSessionHas('cart', $cart);

        $this->assertDatabaseCount('orders', 1);
        Bus::assertNothingDispatched();
    }

    public function test_success_without_session_order_redirects_to_cart(): void
    {
        $this->get(route('cart.checkout.success'))
            ->assertRedirect(route('cart.index'));
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_success_with_deleted_order_clears_stale_reference(): void
    {
        $order = $this->order();
        $id = $order->id;
        $order->delete();
        $cart = ['keep' => ['product_id' => 123, 'quantity' => 1]];

        $this->withSession([
            'last_confirmed_order_id' => $id,
            'cart' => $cart,
        ])->get(route('cart.checkout.success'))
            ->assertRedirect(route('cart.index'))
            ->assertSessionMissing('last_confirmed_order_id')
            ->assertSessionHas('cart', $cart);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_success_preserves_existing_tracking_token(): void
    {
        $order = $this->order();
        $token = $order->tracking_token;

        $this->withSession(['last_confirmed_order_id' => $order->id])
            ->get(route('cart.checkout.success'))
            ->assertOk()
            ->assertViewIs('storefront.checkout-success')
            ->assertViewHas('order', fn (Order $shown) => $shown->is($order));

        $this->assertSame($token, $order->fresh()->tracking_token);
        Bus::assertNothingDispatched();
    }

    public function test_success_generates_missing_tracking_token_and_keeps_it_on_reload(): void
    {
        $order = $this->order(['tracking_token' => null]);

        $this->withSession(['last_confirmed_order_id' => $order->id])
            ->get(route('cart.checkout.success'))
            ->assertOk();

        $token = $order->fresh()->tracking_token;
        $this->assertTrue(Str::isUuid($token));

        $this->get(route('cart.checkout.success'))->assertOk();
        $this->assertSame($token, $order->fresh()->tracking_token);
        $this->assertDatabaseCount('orders', 1);
        Bus::assertNothingDispatched();
    }

    #[DataProvider('customerTypes')]
    public function test_new_checkout_still_creates_order_and_notifies_once(bool $registered): void
    {
        $user = $registered ? User::factory()->create(['role' => 'customer']) : null;
        if ($user) {
            $this->actingAs($user);
        }

        StoreSetting::current()->update([
            'accepting_orders' => true,
            'kitchen_latitude' => -12,
            'kitchen_longitude' => -77,
        ]);

        $category = Category::create([
            'name' => 'Pruebas', 'slug' => 'pruebas', 'is_active' => true,
        ]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chaufa', 'slug' => 'chaufa', 'price' => '20.00',
            'is_available' => true,
        ]);
        $token = (string) Str::uuid();

        $this->withSession([
            'checkout_token' => $token,
            'cart' => ['plate' => [
                'product_id' => $product->id,
                'quantity' => 2,
                'extra_ids' => [],
            ]],
        ])->post(route('cart.checkout.confirm'), [
            'checkout_token' => $token,
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'delivery_address' => 'Direccion de prueba',
            'delivery_latitude' => -12,
            'delivery_longitude' => -77,
            'payment_method' => 'cash',
            'cash_payment_type' => 'exact',
        ])->assertRedirect(route('cart.checkout.success'))
            ->assertSessionHasNoErrors()
            ->assertSessionMissing('cart')
            ->assertSessionMissing('checkout_token');

        $this->assertDatabaseCount('orders', 1);
        $order = Order::firstOrFail();
        $this->assertSame($token, $order->checkout_token);
        $this->assertEquals($user?->id, $order->user_id);
        $this->assertSame('40.00', $order->total);
        $this->assertTrue(Str::isUuid($order->tracking_token));
        $this->assertSame(1, $order->items()->count());
        $this->assertSame(1, $order->statusHistories()->count());
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
        Bus::assertDispatched(
            SendOrderWhatsappNotification::class,
            fn ($job) => $job->orderId === $order->id
        );

        $this->get(route('cart.checkout.success'))
            ->assertOk()
            ->assertViewHas('order', fn (Order $shown) => $shown->is($order));

        // A consumed session token still expires; do not weaken session checks.
        $this->withExceptionHandling();
        $this->post(route('cart.checkout.confirm'), ['checkout_token' => $token])
            ->assertStatus(419);
        $this->assertDatabaseCount('orders', 1);
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
    }

    public function test_corrective_migration_preserves_preexisting_tracking_token(): void
    {
        $order = $this->order();
        $token = $order->tracking_token;
        $migration = require database_path(
            'migrations/2026_10_27_190000_ensure_tracking_token_on_orders_table.php'
        );

        $migration->up();
        $migration->down();

        $this->assertSame($token, $order->fresh()->tracking_token);
    }

    private function order(array $overrides = []): Order
    {
        return Order::create(array_replace([
            'code' => 'CHF-'.Str::ulid(),
            'checkout_token' => (string) Str::uuid(),
            'tracking_token' => (string) Str::uuid(),
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'subtotal' => '20.00',
            'total' => '20.00',
            'payment_method' => 'cash',
            'payment_status' => 'pending',
            'status' => 'pending',
        ], $overrides));
    }
}
