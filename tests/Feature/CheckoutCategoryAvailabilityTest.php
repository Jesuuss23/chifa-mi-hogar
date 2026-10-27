<?php

namespace Tests\Feature;

use App\Jobs\SendOrderWhatsappNotification;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductRecommendation;
use App\Models\StoreSetting;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutCategoryAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;
    private Product $product;
    private ProductRecommendation $recommendation;

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
        $this->category = Category::create([
            'name' => 'Pruebas', 'slug' => 'pruebas', 'is_active' => true,
        ]);
        $this->product = Product::create([
            'category_id' => $this->category->id,
            'name' => 'Chaufa', 'slug' => 'chaufa', 'price' => '20.00',
            'is_available' => true,
        ]);
        $this->recommendation = ProductRecommendation::create([
            'product_id' => $this->product->id,
            'name' => 'Chaufa recomendado', 'slug' => 'chaufa-recomendado',
            'is_active' => true,
        ]);
    }

    public static function unavailableCategories(): array
    {
        return [
            'product / inactive' => ['product', false],
            'recommendation / inactive' => ['recommendation', false],
            'product / soft deleted' => ['product', true],
            'recommendation / soft deleted' => ['recommendation', true],
        ];
    }

    public static function itemTypes(): array
    {
        return ['product' => ['product'], 'recommendation' => ['recommendation']];
    }

    #[DataProvider('unavailableCategories')]
    public function test_service_rejects_preview_quote_and_creation(string $type, bool $deleted): void
    {
        $this->disableCategory($deleted);
        $service = app(OrderService::class);
        $payload = $this->payload($type);
        $payload['checkout_token'] = (string) Str::uuid();

        foreach (['previewLine', 'quote', 'create'] as $method) {
            try {
                $service->{$method}($method === 'previewLine' ? $payload['items'][0] : $payload);
                $this->fail($method.' accepted an unavailable category.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('items', $exception->errors());
            }
        }

        $this->assertNoOrderWasCreated();
    }

    #[DataProvider('unavailableCategories')]
    public function test_cart_confirmation_revalidates_category_and_preserves_cart(
        string $type,
        bool $deleted
    ): void {
        $token = (string) Str::uuid();
        $cart = ['plate' => [
            'type' => $type,
            'product_id' => $this->product->id,
            'recommendation_id' => $type === 'recommendation' ? $this->recommendation->id : null,
            'quantity' => 2,
            'extras_mode' => 'replace',
            'extras' => [],
        ]];

        // The customer has already added the item and received a valid quote.
        $this->assertSame('40.00', app(OrderService::class)->quote($this->payload($type))['total']);
        $this->disableCategory($deleted);

        $this->withSession(['checkout_token' => $token, 'cart' => $cart])
            ->postJson(route('cart.checkout.confirm'), [
                ...$this->payload($type),
                'checkout_token' => $token,
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items')
            ->assertSessionHas('cart', $cart)
            ->assertSessionHas('checkout_token', $token)
            ->assertSessionMissing('last_confirmed_order_id');

        $this->assertNoOrderWasCreated();
    }

    #[DataProvider('unavailableCategories')]
    public function test_json_registration_revalidates_category_after_quote(
        string $type,
        bool $deleted
    ): void {
        $payload = $this->payload($type);
        $token = $this->postJson(route('checkout.quote'), $payload)
            ->assertOk()->json('checkout_token');
        $this->disableCategory($deleted);

        $this->postJson(route('checkout.store'), [
            ...$payload,
            'checkout_token' => $token,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('items')
            ->assertSessionHas('json_checkout_token', $token);

        $this->postJson(route('checkout.quote'), $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('items');

        $this->assertNoOrderWasCreated();
    }

    #[DataProvider('itemTypes')]
    public function test_reactivated_category_can_be_purchased_with_same_checkout(string $type): void
    {
        $payload = $this->payload($type);
        $token = $this->postJson(route('checkout.quote'), $payload)
            ->assertOk()->json('checkout_token');
        $this->category->update(['is_active' => false]);
        $payload['checkout_token'] = $token;
        $this->postJson(route('checkout.store'), $payload)->assertUnprocessable();

        $this->category->update(['is_active' => true]);
        $this->postJson(route('checkout.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('order.total', '40.00');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseHas('order_items', [
            'item_type' => $type,
            'quantity' => 2,
            'line_total' => '40.00',
        ]);
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
    }

    #[DataProvider('itemTypes')]
    public function test_committed_order_can_still_be_retried_after_category_is_disabled(string $type): void
    {
        $payload = $this->payload($type);
        $payload['checkout_token'] = $this->postJson(route('checkout.quote'), $payload)
            ->assertOk()->json('checkout_token');
        $code = $this->postJson(route('checkout.store'), $payload)
            ->assertCreated()->json('order.code');

        $this->category->update(['is_active' => false]);
        $this->postJson(route('checkout.store'), $payload)
            ->assertCreated()
            ->assertJsonPath('order.code', $code)
            ->assertJsonPath('order.total', '40.00');

        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_items', 1);
        $this->assertDatabaseCount('order_status_histories', 1);
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
    }

    private function disableCategory(bool $deleted): void
    {
        if ($deleted) {
            // Exercise the model's soft-delete scope, independently of admin UI.
            $this->category->delete();
        } else {
            $this->category->update(['is_active' => false]);
        }
    }

    private function assertNoOrderWasCreated(): void
    {
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
        $this->assertDatabaseCount('order_status_histories', 0);
        Bus::assertNothingDispatched();
    }

    private function payload(string $type): array
    {
        return [
            'customer_name' => 'Cliente de prueba',
            'customer_phone' => '999999999',
            'delivery_type' => 'delivery',
            'delivery_address' => 'Direccion de prueba',
            'delivery_latitude' => -12,
            'delivery_longitude' => -77,
            'payment_method' => 'cash',
            'cash_payment_type' => 'exact',
            'items' => [[
                'type' => $type,
                'id' => $type === 'product' ? $this->product->id : $this->recommendation->id,
                'quantity' => 2,
            ]],
        ];
    }
}
