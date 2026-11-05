<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CheckoutFeedbackTest extends TestCase
{
    use RefreshDatabase;

    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array', 'turnstile.enabled' => false]);
        Http::preventStrayRequests();
        $this->token = (string) Str::uuid();
        $category = Category::create(['name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Chaufa', 'slug' => 'chaufa', 'price' => 20, 'is_available' => true]);
        $this->withSession(['checkout_token' => $this->token, 'cart' => [
            'plate' => ['product_id' => $product->id, 'quantity' => 1, 'extra_ids' => []],
        ]]);
    }

    public function test_errors_link_to_visible_controls_and_escape_messages(): void
    {
        $errors = (new ViewErrorBag())->put('default', new MessageBag([
            'customer_phone' => ['Revisa el celular.'],
            'delivery_latitude' => ['Selecciona la ubicación.'],
            'items' => ['<script>alert(1)</script>'],
        ]));
        $this->withSession(['errors' => $errors])->get(route('cart.checkout'))
            ->assertOk()
            ->assertSee('id="checkout-errors"', false)
            ->assertSee('href="#customer_phone"', false)
            ->assertSee('href="#btn-select-map"', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('href="#items"', false);
        Http::assertNothingSent();
    }

    public function test_quote_amounts_and_status_are_rendered_for_progressive_feedback(): void
    {
        $this->withSession(['checkout_quote' => ['subtotal' => 20, 'delivery_fee' => 5, 'total' => 25]])
            ->get(route('cart.checkout'))->assertOk()
            ->assertSee('id="checkout-quote-amounts"', false)
            ->assertSee('id="checkout-quote-notice" role="status"', false)
            ->assertSee('25.00')
            ->assertDontSee('id="checkout-errors"', false);
    }

    public function test_checkout_without_quote_does_not_render_amounts(): void
    {
        $this->get(route('cart.checkout'))->assertOk()
            ->assertDontSee('id="checkout-quote-amounts"', false);
    }
}
