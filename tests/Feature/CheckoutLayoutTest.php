<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutLayoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array']);
        Http::preventStrayRequests();
        $category = Category::create(['name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Chaufa',
            'slug' => 'chaufa', 'price' => 20, 'is_available' => true]);
        $this->withSession(['checkout_token' => (string) Str::uuid(), 'cart' => [
            'plate' => ['product_id' => $product->id, 'quantity' => 1, 'extra_ids' => []],
        ]]);
    }

    public static function layouts(): array
    {
        return [
            'legal published without quote' => [true, false],
            'legal unpublished without quote' => [false, false],
            'legal published with quote' => [true, true],
            'legal unpublished with quote' => [false, true],
        ];
    }

    #[DataProvider('layouts')]
    public function test_optional_content_never_displaces_fields_or_duplicates_quote(bool $legal, bool $quote): void
    {
        StoreSetting::current()->update([
            'legal_pages_published' => $legal, 'legal_address' => 'Dirección de prueba',
            'privacy_hosting' => 'Proveedor de prueba', 'privacy_retention' => 'Plazo de prueba',
            'privacy_database' => 'Banco de prueba',
        ]);
        if ($quote) {
            $this->withSession(['checkout_quote' => ['subtotal' => 20, 'delivery_fee' => 5, 'total' => 25]]);
        }
        $response = $this->get(route('cart.checkout'))->assertOk();
        $html = $response->getContent();
        $dom = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        try {
            $dom->loadHTML('<?xml encoding="UTF-8">'.$html);
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
        $xpath = new \DOMXPath($dom);
        $layout = $xpath->query('//form[@id="checkout-form"]/div[contains(@class, "checkout-layout")]');
        $this->assertSame(1, $layout->length);
        $columns = $xpath->query('./*', $layout->item(0));
        $this->assertSame(2, $columns->length, 'Only fields and summary belong in the grid.');
        $this->assertSame('div', $columns->item(0)->nodeName);
        $this->assertSame('aside', $columns->item(1)->nodeName);
        foreach (['customer_name', 'customer_phone', 'customer_email', 'delivery_address', 'delivery-map', 'payment-section', 'customer_notes'] as $id) {
            $this->assertSame(1, $xpath->query('.//*[@id="'.$id.'"]', $columns->item(0))->length, $id);
        }
        foreach (['quote-button', 'confirm-button'] as $id) {
            $this->assertSame(1, $xpath->query('.//*[@id="'.$id.'"]', $columns->item(1))->length, $id);
        }
        $privacy = $xpath->query('.//p[contains(@class, "checkout-privacy")]', $columns->item(1));
        $this->assertSame($legal ? 1 : 0, $privacy->length);
        $this->assertSame($quote ? 1 : 0, $xpath->query('//*[@id="checkout-quote-amounts"]')->length);
        if ($quote) {
            $this->assertSame(1, $xpath->query('.//*[@id="checkout-quote-amounts"]', $columns->item(1))->length);
            $this->assertSame(1, substr_count($html, 'S/ 25.00'), 'An invalidated quote must not leave a second total visible.');
        }
        $response->assertSee('action="'.route('cart.checkout.quote').'"', false)
            ->assertSee('formaction="'.route('cart.checkout.confirm').'"', false)
            ->assertSee('name="_token"', false)->assertSee('name="checkout_token"', false)
            ->assertSee('name="delivery_type"', false);
        Http::assertNothingSent();
    }

    public function test_login_shortcuts_still_preserve_draft_and_skip_incomplete_validation(): void
    {
        $this->get(route('cart.checkout'))->assertOk()
            ->assertSee('formaction="'.route('cart.checkout.access', 'login').'"', false)
            ->assertSee('formaction="'.route('cart.checkout.access', 'register').'"', false)
            ->assertSee('formnovalidate data-checkout-auth="true"', false)
            ->assertSee('href="#customer_name"', false);
    }
}
