<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use App\Services\CheckoutAuthDraft;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutAuthDraftTest extends TestCase
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

    public function test_login_restores_delivery_without_changing_profile(): void
    {
        $user = $this->customer();
        $before = $user->customerProfile->getAttributes();
        $this->save(['customer_name' => 'Destinatario', 'delivery_address' => 'Otra dirección', 'delivery_latitude' => '-11', 'delivery_longitude' => '-76', 'customer_notes' => 'Sin cebolla']);
        $this->get(route('login', ['continue' => 'checkout']))->assertOk();
        $this->post(route('customer.login.store'), ['email' => $user->email, 'password' => 'correct-password'])
            ->assertRedirect(route('cart.checkout'));
        $response = $this->get(route('cart.checkout'))->assertOk()->assertSee('Recuperamos los datos');
        $this->assertSame('Destinatario', $this->value($response->getContent(), 'customer_name'));
        $this->assertSame('Otra dirección', $this->value($response->getContent(), 'delivery_address'));
        $this->assertSame('-11', $this->value($response->getContent(), 'delivery_latitude'));
        $response->assertSee('Sin cebolla')->assertDontSee('Esta es la dirección y ubicación guardadas');
        $this->assertSame($before, $user->customerProfile->fresh()->getAttributes());
        $this->assertSame($user->id, session(CheckoutAuthDraft::KEY.'.user_id'));
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
    }

    public function test_partial_location_never_borrows_profile_coordinates(): void
    {
        $user = $this->customer();
        $this->save(['delivery_address' => 'Otra calle', 'delivery_latitude' => '-11']);
        $html = $this->actingAs($user)->get(route('cart.checkout'))->assertOk()->getContent();
        $this->assertSame('Otra calle', $this->value($html, 'delivery_address'));
        $this->assertSame('', $this->value($html, 'delivery_latitude'));
        $this->assertSame('', $this->value($html, 'delivery_longitude'));
    }

    public function test_blank_contact_and_destination_use_profile(): void
    {
        $user = $this->customer();
        $this->save(['customer_name' => '', 'delivery_address' => '', 'delivery_reference' => '']);
        $html = $this->actingAs($user)->get(route('cart.checkout'))->assertOk()->getContent();
        $this->assertSame($user->name, $this->value($html, 'customer_name'));
        $this->assertSame('Guardada', $this->value($html, 'delivery_address'));
    }

    public function test_register_destination_and_guest_return_preserve_partial_text(): void
    {
        $this->post(route('cart.checkout.access', 'register'), [
            'checkout_token' => $this->token, 'customer_phone' => '999',
        ])->assertRedirect(route('customer.register', ['continue' => 'checkout']));
        $html = $this->get(route('cart.checkout'))->assertOk()->getContent();
        $this->assertSame('999', $this->value($html, 'customer_phone'));
        $this->assertGuest();
        $this->assertSame($this->token, session('checkout_token'));
    }

    public function test_only_allowed_fields_saved_and_old_quote_removed(): void
    {
        $this->withSession(['checkout_quote' => ['total' => 1], '_old_input' => ['customer_name' => 'Old']]);
        $this->save(['customer_notes' => 'Nota', 'role' => 'admin', 'user_id' => 123, 'total' => 1, 'password' => 'secret']);
        $data = session(CheckoutAuthDraft::KEY.'.data');
        foreach (['role', 'user_id', 'total', 'password', 'checkout_token'] as $field) {
            $this->assertArrayNotHasKey($field, $data);
        }
        $this->assertNull(session('checkout_quote'));
        $this->assertNull(session('_old_input'));
    }

    #[DataProvider('invalidDrafts')]
    public function test_invalid_draft_discarded(string $kind): void
    {
        $this->save(['customer_name' => 'Stale name']);
        $draft = session(CheckoutAuthDraft::KEY);
        if ($kind === 'expired') {
            $draft['created_at'] = now()->timestamp - 1801;
        } elseif ($kind === 'future') {
            $draft['created_at'] = now()->timestamp + 1;
        } elseif ($kind === 'token') {
            $draft['checkout_token'] = (string) Str::uuid();
        } else {
            $draft['user_id'] = 999999;
        }
        $this->withSession([CheckoutAuthDraft::KEY => $draft])->get(route('cart.checkout'))->assertOk()
            ->assertDontSee('Stale name')->assertSessionMissing(CheckoutAuthDraft::KEY);
    }

    public static function invalidDrafts(): array
    {
        return [['expired'], ['future'], ['token'], ['owner']];
    }

    public function test_bad_token_and_array_input_cannot_save(): void
    {
        $this->post(route('cart.checkout.access', 'login'), ['checkout_token' => (string) Str::uuid()])->assertStatus(419);
        $this->post(route('cart.checkout.access', 'login'), ['checkout_token' => $this->token, 'customer_name' => ['not a string']])
            ->assertSessionHasErrors('customer_name')->assertSessionMissing(CheckoutAuthDraft::KEY);
    }

    public function test_empty_cart_cleans_draft(): void
    {
        $this->save(['customer_notes' => 'Nota']);
        $this->withSession(['cart' => []])->post(route('cart.checkout.access', 'login'), ['checkout_token' => $this->token])
            ->assertRedirect(route('cart.index'))->assertSessionMissing(CheckoutAuthDraft::KEY);
    }

    public function test_current_input_wins_without_mixing_coordinates(): void
    {
        $this->save(['delivery_address' => 'Borrador', 'delivery_latitude' => '-11', 'delivery_longitude' => '-76']);
        $html = $this->withSession(['_old_input' => ['delivery_address' => 'Nueva edición', 'delivery_latitude' => '']])
            ->get(route('cart.checkout'))->assertOk()->getContent();
        $this->assertSame('Nueva edición', $this->value($html, 'delivery_address'));
        $this->assertSame('', $this->value($html, 'delivery_longitude'));
    }

    public function test_quote_clears_draft_and_validates_delivery(): void
    {
        $this->save(['customer_notes' => 'Borrador']);
        $this->post(route('cart.checkout.quote'), ['delivery_type' => 'delivery'])
            ->assertSessionHasErrors('delivery_address')->assertSessionMissing(CheckoutAuthDraft::KEY);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_zero_coordinates_preserved(): void
    {
        $this->save(['delivery_address' => 'Punto cero', 'delivery_latitude' => '0', 'delivery_longitude' => '0']);
        $this->assertSame('0', session(CheckoutAuthDraft::KEY.'.data.delivery_latitude'));
        $this->assertSame('0', session(CheckoutAuthDraft::KEY.'.data.delivery_longitude'));
    }

    private function customer(): User
    {
        $user = User::factory()->create(['role' => 'customer', 'password' => 'correct-password']);
        $user->customerProfile()->create(['phone' => '999999999', 'delivery_address' => 'Guardada',
            'delivery_latitude' => -12, 'delivery_longitude' => -77]);
        return $user;
    }

    private function save(array $data): void
    {
        $this->post(route('cart.checkout.access', 'login'), ['checkout_token' => $this->token, ...$data])
            ->assertRedirect(route('login', ['continue' => 'checkout']))->assertSessionHasNoErrors();
    }

    private function value(string $html, string $name): string
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
}
