<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CustomerInterfaceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'turnstile.enabled' => false]);
        $this->withoutVite();
    }

    public function test_login_keeps_email_authentication_and_has_no_strength_meter(): void
    {
        $this->get(route('login'))->assertOk()
            ->assertSee('name="email"', false)->assertSee('autocomplete="current-password"', false)
            ->assertSee('data-password-toggle', false)->assertDontSee('data-password-meter', false)
            ->assertSee('name="_token"', false)->assertSee(route('customer.password.request'), false);
    }

    public function test_registration_preserves_labels_confirmation_and_server_errors(): void
    {
        $this->get(route('customer.register'))->assertOk()
            ->assertSee('for="password"', false)->assertSee('placeholder="Mínimo 8 caracteres"', false)
            ->assertSee('minlength="8"', false)->assertSee('name="password_confirmation"', false)
            ->assertSee('data-password-meter', false)->assertSee('name="_token"', false);
        $errors = (new ViewErrorBag)->put('default', new MessageBag(['password' => 'Las contraseñas no coinciden.']));
        $this->withSession(['errors' => $errors])->get(route('customer.register'))->assertOk()
            ->assertSee('Las contraseñas no coinciden.')->assertSee('id="password-error"', false);
    }

    public function test_reset_keeps_its_token_and_new_password_controls(): void
    {
        $this->get(route('customer.password.reset', ['token' => 'example-token', 'email' => 'test@example.com']))
            ->assertOk()->assertSee('name="token"', false)->assertSee('value="example-token"', false)
            ->assertSee('autocomplete="new-password"', false)->assertSee('data-password-meter', false);
    }

    public function test_offer_badge_is_only_shown_for_a_real_saving(): void
    {
        $category = Category::create(['name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true]);
        $product = Product::create(['category_id' => $category->id, 'name' => 'Chaufa',
            'slug' => 'chaufa', 'price' => 20, 'is_available' => true]);
        $combo = Combo::create(['name' => 'Familiar', 'slug' => 'familiar', 'price' => 35,
            'is_available' => true, 'show_in_menu' => true]);
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 2]);
        foreach (['storefront.index', 'combos.index'] as $route) {
            $this->get(route($route))->assertOk()->assertSee('Combos y promociones')
                ->assertSee('data-combo-offer', false)->assertSee('class="promo-border"', false)
                ->assertSee('<del>S/ 40.00</del>', false);
        }
        foreach ([40, 45] as $price) {
            $combo->update(['price' => $price]);
            $this->get(route('storefront.index'))->assertOk()
                ->assertDontSee('data-combo-offer', false)->assertDontSee('class="promo-border"', false);
        }
    }

    public function test_header_preserves_guest_account_and_cart_access(): void
    {
        $this->withSession(['cart' => ['example' => ['quantity' => 3]]])->get(route('storefront.index'))
            ->assertOk()->assertSee('Ver carrito, 3 unidades')->assertSee('data-customer-menu', false)
            ->assertSee(route('login'), false)->assertSee(route('customer.register'), false)
            ->assertSee('href="#main-content"', false);
    }

    public function test_customer_avatar_and_logout_remain_private_routes(): void
    {
        $user = User::factory()->create(['role' => 'customer']);
        $user->forceFill(['avatar_path' => 'private/example.jpg'])->save();
        $this->actingAs($user)->get(route('storefront.index'))->assertOk()
            ->assertSee('src="'.route('customer.avatar.show').'"', false)
            ->assertSee(route('customer.logout'), false)->assertDontSee('private/example.jpg');
    }

    public function test_footer_keeps_published_legal_links_and_configured_contact(): void
    {
        StoreSetting::current()->update([
            'legal_pages_published' => true, 'legal_address' => 'Dirección de prueba',
            'privacy_hosting' => 'Proveedor de prueba', 'privacy_retention' => 'Plazo de prueba',
            'privacy_database' => 'Banco de datos de prueba', 'privacy_email' => 'contact@example.com',
        ]);
        $this->get(route('storefront.index'))->assertOk()->assertSee('mailto:contact@example.com', false)
            ->assertSee(route('legal.notice'), false)->assertSee(route('legal.privacy'), false)
            ->assertSee(route('legal.cookies'), false);
        $this->get(route('combos.index'))->assertOk()->assertSee('class="customer-footer catalog-surface"', false);
    }
}
