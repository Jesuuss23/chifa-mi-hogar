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
use Tests\TestCase;

class CustomerCheckoutDefaultsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Bus::fake([SendOrderWhatsappNotification::class]);
    }

    private function customer(): User
    {
        $user = User::factory()->create(['role' => 'customer', 'name' => 'Mi nombre']);
        $user->customerProfile()->create([
            'phone' => '999999999', 'delivery_address' => 'Dirección guardada',
            'delivery_reference' => 'Referencia guardada',
            'delivery_latitude' => -12, 'delivery_longitude' => -77,
        ]);
        return $user;
    }

    private function cart(): array
    {
        $category = Category::create(['name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Chaufa', 'slug' => 'chaufa',
            'price' => 20, 'is_available' => true,
        ]);
        return ['plate' => ['product_id' => $product->id, 'quantity' => 1, 'extra_ids' => []]];
    }

    private function inputValue(string $html, string $name): string
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>'.$html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        $nodes = (new \DOMXPath($doc))->query('//input[@name="'.$name.'"]');
        $this->assertCount(1, $nodes);
        return $nodes->item(0)->getAttribute('value');
    }

    public function test_saved_contact_and_delivery_fill_checkout(): void
    {
        $user = $this->customer();
        $response = $this->actingAs($user)->withSession(['cart' => $this->cart()])
            ->get(route('cart.checkout'))->assertOk()->assertSee('Esta es la dirección y ubicación guardadas');
        $html = $response->getContent();
        $this->assertSame('Mi nombre', $this->inputValue($html, 'customer_name'));
        $this->assertSame($user->email, $this->inputValue($html, 'customer_email'));
        $this->assertSame('999999999', $this->inputValue($html, 'customer_phone'));
        $this->assertSame('Dirección guardada', $this->inputValue($html, 'delivery_address'));
        $this->assertSame('-12.0000000', $this->inputValue($html, 'delivery_latitude'));
    }

    public function test_old_input_wins_and_partial_location_does_not_reuse_saved_coordinates(): void
    {
        $this->actingAs($this->customer())->withSession([
            'cart' => $this->cart(),
            '_old_input' => ['customer_name' => 'Otro nombre', 'delivery_address' => 'Otra calle', 'delivery_latitude' => ''],
        ]);
        $html = $this->get(route('cart.checkout'))->assertOk()->getContent();
        $this->assertSame('Otro nombre', $this->inputValue($html, 'customer_name'));
        $this->assertSame('Otra calle', $this->inputValue($html, 'delivery_address'));
        $this->assertSame('', $this->inputValue($html, 'delivery_latitude'));
        $this->assertSame('', $this->inputValue($html, 'delivery_longitude'));
        $this->assertSame('', $this->inputValue($html, 'delivery_reference'));
    }

    public function test_guest_receives_no_saved_values(): void
    {
        $this->customer();
        $html = $this->withSession(['cart' => $this->cart()])
            ->get(route('cart.checkout'))->assertOk()->getContent();
        foreach (['customer_name', 'customer_email', 'customer_phone', 'delivery_address', 'delivery_latitude'] as $name) {
            $this->assertSame('', $this->inputValue($html, $name));
        }
    }

    public function test_new_customer_without_profile_still_can_checkout(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $html = $this->actingAs($user)->withSession(['cart' => $this->cart()])
            ->get(route('cart.checkout'))->assertOk()->getContent();
        $this->assertSame($user->email, $this->inputValue($html, 'customer_email'));
        $this->assertSame('', $this->inputValue($html, 'delivery_latitude'));
    }

    public function test_order_specific_address_does_not_overwrite_profile(): void
    {
        $user = $this->customer();
        $before = $user->customerProfile->getAttributes();
        StoreSetting::current()->update([
            'accepting_orders' => true, 'kitchen_latitude' => -12, 'kitchen_longitude' => -77,
        ]);
        $token = (string) Str::uuid();
        $this->actingAs($user)->withSession(['cart' => $this->cart(), 'checkout_token' => $token])
            ->post(route('cart.checkout.confirm'), [
                'checkout_token' => $token, 'customer_name' => 'Destinatario',
                'customer_phone' => '988888888', 'delivery_type' => 'delivery',
                'delivery_address' => 'Dirección solo del pedido', 'delivery_reference' => 'Otra puerta',
                'delivery_latitude' => -12.001, 'delivery_longitude' => -77.001,
                'payment_method' => 'cash', 'cash_payment_type' => 'exact',
            ])->assertRedirect(route('cart.checkout.success'))->assertSessionHasNoErrors();
        $this->assertSame($before, $user->customerProfile->fresh()->getAttributes());
        $this->assertSame('Mi nombre', $user->fresh()->name);
        $this->assertSame('Dirección solo del pedido', Order::firstOrFail()->delivery_address);
        $this->assertSame($user->id, Order::firstOrFail()->user_id);
        Bus::assertDispatchedTimes(SendOrderWhatsappNotification::class, 1);
    }
}
