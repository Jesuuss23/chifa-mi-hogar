<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Extra;
use App\Models\Order;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use App\Services\OrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutSecurityTest extends TestCase
{
    use RefreshDatabase;

    private Category $category;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Bus::fake();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array']);
        StoreSetting::current()->update(['accepting_orders' => true, 'kitchen_latitude' => 0, 'kitchen_longitude' => 0]);
        $this->category = Category::create(['name' => 'Carta', 'slug' => 'carta', 'is_active' => true]);
        $this->product = $this->product();
    }

    private function product(): Product
    {
        return Product::create(['category_id' => $this->category->id, 'name' => 'Chaufa', 'slug' => (string) Str::uuid(),
            'price' => 20, 'is_available' => true]);
    }

    private function fullCart(int $size = 30): array
    {
        $cart = [];
        for ($i = 0; $i < $size; $i++) {
            $p = $this->product();
            $cart[hash('sha256', $p->id.':')] = ['product_id' => $p->id, 'quantity' => 1, 'extra_ids' => []];
        }

        return $cart;
    }

    private function data(array $overrides = []): array
    {
        return array_replace(['checkout_token' => (string) Str::uuid(),
            'customer_name' => 'Cliente', 'customer_phone' => '999999999',
            'delivery_type' => 'delivery', 'delivery_address' => 'Calle 1',
            'delivery_latitude' => 0, 'delivery_longitude' => 0, 'payment_method' => 'yape',
            'items' => [['type' => 'product', 'id' => $this->product->id, 'quantity' => 1]]], $overrides);
    }

    public function test_traditional_cart_rejects_a_31st_line_without_changing_session(): void
    {
        $cart = $this->fullCart();
        $this->withSession(['cart' => $cart])->postJson(route('cart.store'),
            ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('cart')->assertSessionHas('cart', $cart);
    }

    public function test_combo_cart_has_the_same_limit_as_products(): void
    {
        $combo = Combo::create(['name' => 'Duo', 'slug' => 'duo', 'price' => 30, 'is_available' => true, 'show_in_menu' => true]);
        $combo->items()->create(['product_id' => $this->product->id, 'quantity' => 2]);
        $cart = $this->fullCart();
        $this->withSession(['cart' => $cart])->postJson(route('cart.combos.store'), ['combo_id' => $combo->id, 'quantity' => 1])
            ->assertUnprocessable()->assertJsonValidationErrors('cart')->assertSessionHas('cart', $cart);
    }

    public function test_multi_unit_add_is_atomic_when_new_configurations_exceed_limit(): void
    {
        $extra = Extra::create(['name' => 'Wantan', 'price' => 2, 'is_available' => true]);
        $this->product->extras()->attach($extra);
        $cart = $this->fullCart(29);
        $unit = ['type' => 'product', 'id' => $this->product->id, 'quantity' => 1, 'mode' => 'edit', 'extras' => []];
        $other = array_replace($unit, ['extras' => [['id' => $extra->id, 'quantity' => 1]]]);
        $this->withSession(['cart' => $cart])->postJson(route('cart.store'), ['product_id' => $this->product->id,
            'quantity' => 2, 'units' => [$unit, $other]])
            ->assertUnprocessable()->assertJsonValidationErrors('cart')->assertSessionHas('cart', $cart);
    }

    public function test_full_cart_can_increase_existing_line_or_remove_it(): void
    {
        $cart = $this->fullCart();
        $key = array_key_first($cart);
        $id = $cart[$key]['product_id'];
        $this->withSession(['cart' => $cart])->post(route('cart.store'), ['product_id' => $id, 'quantity' => 1])
            ->assertSessionHasNoErrors()->assertSessionHas('cart.'.$key.'.quantity', 2);
        $this->assertCount(30, session('cart'));
        $this->delete(route('cart.destroy', $key))->assertSessionHasNoErrors();
        $this->assertCount(29, session('cart'));
    }

    public function test_legacy_additional_list_is_bounded_before_catalog_lookup(): void
    {
        $ids = [];
        for ($i = 0; $i < 31; $i++) {
            $extra = Extra::create(['name' => 'Extra '.$i, 'price' => 1, 'is_available' => true]);
            $this->product->extras()->attach($extra);
            $ids[] = $extra->id;
        }
        $this->postJson(route('cart.store'), ['product_id' => $this->product->id, 'quantity' => 1, 'extras' => $ids])
            ->assertUnprocessable()->assertJsonValidationErrors('extras');
    }

    public function test_oversized_json_is_rejected_before_nested_validation_or_cart_writes(): void
    {
        $cart = ['original' => ['product_id' => $this->product->id, 'quantity' => 1, 'extra_ids' => []]];
        $this->withSession(['cart' => $cart]);
        foreach (['cart.store', 'cart.combos.store', 'checkout.quote', 'checkout.store', 'cart.checkout.quote', 'cart.checkout.confirm'] as $route) {
            $this->postJson(route($route), ['junk' => str_repeat('a', 131073)])
                ->assertStatus(413)->assertSessionHas('cart', $cart);
        }
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_preexisting_oversized_cart_can_still_be_reduced(): void
    {
        $cart = $this->fullCart(32);
        $this->withSession(['cart' => $cart])->delete(route('cart.destroy', array_key_first($cart)))
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertCount(31, session('cart'));
    }

    public function test_multipart_cannot_bypass_the_checkout_body_limit(): void
    {
        $this->withHeaders(['Content-Type' => 'multipart/form-data; boundary=test'])
            ->post(route('cart.store'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertStatus(415);
        $this->assertEmpty(session('cart', []));
    }

    public function test_cart_mutations_share_a_rate_limit(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->delete(route('cart.destroy', 'absent'))->assertRedirect();
        }
        $this->postJson(route('cart.store'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertStatus(429)->assertHeader('Retry-After');
        $this->travel(61)->seconds();
        $this->post(route('cart.store'), ['product_id' => $this->product->id, 'quantity' => 1])
            ->assertRedirect()->assertSessionHasNoErrors();
    }

    public static function readers(): array
    {
        return ['other customer' => ['customer'], 'guest' => [null]];
    }

    #[DataProvider('readers')]
    public function test_old_session_reference_cannot_expose_another_accounts_confirmation_or_status(?string $role): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $this->actingAs($owner);
        $order = app(OrderService::class)->create($this->data());
        auth()->logout();
        if ($role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
        }
        $this->withSession(['last_confirmed_order_id' => $order->id])->get(route('cart.checkout.success'))
            ->assertNotFound()->assertDontSee($order->tracking_token);
        $this->withSession(['last_confirmed_order_id' => $order->id])->getJson(route('cart.checkout.status'))->assertNotFound();
    }

    #[DataProvider('readers')]
    public function test_old_checkout_token_does_not_replay_another_accounts_order(?string $role): void
    {
        $owner = User::factory()->create(['role' => 'customer']);
        $this->actingAs($owner);
        $data = $this->data();
        $order = app(OrderService::class)->create($data);
        auth()->logout();
        if ($role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
        }
        $this->withSession(['checkout_token' => $data['checkout_token']])
            ->post(route('cart.checkout.confirm'), ['checkout_token' => $data['checkout_token']])->assertNotFound();
        $this->withSession(['json_checkout_token' => $data['checkout_token']])
            ->postJson(route('checkout.store'), $data)->assertNotFound()->assertJsonMissingPath('order');
        $this->assertDatabaseCount('orders', 1);
        $this->assertSame($owner->id, (int) $order->refresh()->user_id);
    }

    public function test_guest_order_remains_accessible_in_its_session_after_login(): void
    {
        $data = $this->data();
        $order = app(OrderService::class)->create($data);
        $this->actingAs(User::factory()->create(['role' => 'customer']));
        $this->withSession(['last_confirmed_order_id' => $order->id])
            ->get(route('cart.checkout.success'))->assertOk();
        $this->withSession(['json_checkout_token' => $data['checkout_token']])
            ->postJson(route('checkout.store'), $data)->assertCreated()->assertJsonPath('order.code', $order->code);
        $this->assertNull($order->refresh()->user_id);
    }

    public function test_json_checkout_ignores_prices_roles_and_payment_status_sent_by_client(): void
    {
        $customer = User::factory()->create(['role' => 'customer']);
        $this->actingAs($customer);
        $data = $this->data(['total' => 0, 'subtotal' => 0, 'discount_amount' => 99999, 'delivery_fee' => -100,
            'user_id' => 99999, 'role' => 'admin', 'payment_status' => 'verified', 'status' => 'delivered']);
        $data['items'][0]['price'] = 0;
        $data['items'][0]['line_total_cents'] = 1;
        $quote = $this->postJson(route('checkout.quote'), $data)->assertOk()->assertJsonPath('quote.total', '20.00');
        $data['checkout_token'] = $quote->json('checkout_token');
        $this->postJson(route('checkout.store'), $data)->assertCreated()->assertJsonPath('order.total', '20.00')
            ->assertJsonPath('order.payment_status', 'pending')->assertJsonPath('order.status', 'pending');
        $this->assertEquals($customer->id, Order::firstOrFail()->user_id);
    }

    public function test_foreign_additional_and_negative_or_array_quantities_do_not_create_orders(): void
    {
        $extra = Extra::create(['name' => 'Otro', 'price' => 2, 'is_available' => true]);
        $data = $this->data();
        $data['items'][0]['extras'] = [['id' => $extra->id, 'quantity' => 1]];
        $this->postJson(route('checkout.quote'), $data)->assertUnprocessable()->assertJsonValidationErrors('items');
        foreach ([-1, 0, 51, ['bad']] as $quantity) {
            $bad = $this->data();
            $bad['items'][0]['quantity'] = $quantity;
            $this->postJson(route('checkout.quote'), $bad)->assertUnprocessable();
        }
        $this->assertDatabaseCount('orders', 0);
    }
}
