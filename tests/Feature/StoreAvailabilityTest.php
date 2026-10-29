<?php

namespace Tests\Feature;

use App\Jobs\SendOrderWhatsappNotification;
use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\DeliveryService;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StoreAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Bus::fake([SendOrderWhatsappNotification::class]);
        StoreSetting::current()->update([
            'accepting_orders' => false,
            'closed_message' => 'Volvemos esta noche.',
        ]);
    }

    public static function catalogRoutes(): array
    {
        return [['storefront.index'], ['combos.index']];
    }

    #[DataProvider('catalogRoutes')]
    public function test_closed_catalog_stays_available_with_notice(string $route): void
    {
        $this->get(route($route))->assertOk()
            ->assertSee('id="store-closed-notice"', false)
            ->assertSee('Volvemos esta noche.')
            ->assertSee('Los pedidos ya registrados mantienen su seguimiento.');
    }

    public function test_checkout_disables_submission_then_reenables_it_after_reopening(): void
    {
        $cart = $this->cart();
        $this->withSession(['cart' => $cart]);
        $response = $this->get(route('cart.checkout'))->assertOk()
            ->assertSee('Volvemos esta noche.')
            ->assertSessionHas('cart', $cart);
        foreach (['quote-button', 'confirm-button'] as $id) {
            $this->assertMatchesRegularExpression('/<button\b[^>]*id="'.$id.'"[^>]*\bdisabled\b/s', $response->getContent());
        }

        StoreSetting::current()->update(['accepting_orders' => true]);
        $response = $this->get(route('cart.checkout'))->assertOk()
            ->assertDontSee('id="store-closed-notice"', false)
            ->assertSessionHas('cart', $cart);
        foreach (['quote-button', 'confirm-button'] as $id) {
            preg_match('/<button\b[^>]*id="'.$id.'"[^>]*>/s', $response->getContent(), $matches);
            $this->assertNotEmpty($matches);
            $this->assertStringNotContainsString('disabled', $matches[0]);
        }
    }

    public static function htmlEndpoints(): array
    {
        return [['cart.checkout.quote'], ['cart.checkout.confirm']];
    }

    #[DataProvider('htmlEndpoints')]
    public function test_close_after_checkout_load_rejects_post_and_preserves_cart(string $route): void
    {
        StoreSetting::current()->update(['accepting_orders' => true]);
        $cart = $this->cart();
        $token = (string) Str::uuid();
        $this->withSession(['cart' => $cart, 'checkout_token' => $token])
            ->get(route('cart.checkout'))->assertOk();
        StoreSetting::current()->update(['accepting_orders' => false]);
        $this->mock(DeliveryService::class)->shouldNotReceive('calculate');

        $this->from(route('cart.checkout'))->post(route($route), $this->data($token))
            ->assertRedirect(route('cart.checkout'))
            ->assertSessionHasErrors(['store' => 'Volvemos esta noche.'])
            ->assertSessionHas('cart', $cart)
            ->assertSessionHas('checkout_token', $token);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        Bus::assertNothingDispatched();
    }

    public static function jsonEndpoints(): array
    {
        return [['/checkout/quote'], ['/checkout']];
    }

    #[DataProvider('jsonEndpoints')]
    public function test_json_cannot_bypass_closure(string $path): void
    {
        $cart = $this->cart();
        $token = (string) Str::uuid();
        $data = $this->data($token);
        $data['items'] = [['type' => 'product', 'id' => $cart['plate']['product_id'], 'quantity' => 1]];
        $this->mock(DeliveryService::class)->shouldNotReceive('calculate');

        $this->withSession(['json_checkout_token' => $token])->postJson($path, $data)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('store')
            ->assertJsonPath('errors.store.0', 'Volvemos esta noche.');
        $this->assertDatabaseCount('orders', 0);
        Bus::assertNothingDispatched();
    }

    public function test_direct_order_creation_is_blocked_before_calculation(): void
    {
        $this->mock(DeliveryService::class)->shouldNotReceive('calculate');
        try {
            app(OrderService::class)->create($this->data((string) Str::uuid()));
            $this->fail('Closed store accepted a new order.');
        } catch (ValidationException $exception) {
            $this->assertSame(['Volvemos esta noche.'], $exception->errors()['store']);
        }
        $this->assertDatabaseCount('orders', 0);
        Bus::assertNothingDispatched();
    }

    public function test_blank_message_uses_same_fallback_in_notice_and_validation(): void
    {
        StoreSetting::current()->update(['closed_message' => '   ']);
        $message = 'Por el momento no estamos recibiendo pedidos.';
        $this->get(route('storefront.index'))->assertOk()->assertSee($message);

        try {
            app(OrderService::class)->quote([]);
            $this->fail('Closed store returned a quote.');
        } catch (ValidationException $exception) {
            $this->assertSame([$message], $exception->errors()['store']);
        }
    }

    public function test_custom_notice_is_html_escaped(): void
    {
        $message = '<script>alert("closed")</script>';
        StoreSetting::current()->update(['closed_message' => $message]);
        $this->get(route('storefront.index'))->assertOk()
            ->assertSee(e($message), false)
            ->assertDontSee($message, false);
    }

    private function cart(): array
    {
        $category = Category::create(['name' => 'Carta', 'slug' => 'carta', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chaufa', 'slug' => 'chaufa',
            'price' => '20.00', 'is_available' => true,
        ]);

        return ['plate' => ['product_id' => $product->id, 'quantity' => 1, 'extra_ids' => []]];
    }

    private function data(string $token): array
    {
        return [
            'checkout_token' => $token,
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'delivery_address' => 'Direccion de prueba',
            'delivery_latitude' => -12,
            'delivery_longitude' => -77,
            'payment_method' => 'cash',
            'cash_payment_type' => 'exact',
        ];
    }
}
