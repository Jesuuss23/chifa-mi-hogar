<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SageThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        StoreSetting::current();
    }

    public function test_admin_shell_keeps_navigation_logout_and_private_headers(): void
    {
        StoreSetting::current()->forceFill(['storefront_theme' => 'christmas'])->save();
        $admin = User::factory()->create(['role' => 'admin', 'name' => '<script>bad()</script>']);
        $response = $this->actingAs($admin)->get(route('admin.customers.index'))->assertOk()
            ->assertSee('data-admin-theme="sage"', false)
            ->assertDontSee('data-theme="christmas"', false)
            ->assertSee('Operación')->assertSee('Carta')->assertSee('Negocio')->assertSee('Opiniones')
            ->assertSee('id="admin-content"', false)
            ->assertDontSee('<script>bad()</script>', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        foreach ([
            'admin.dashboard', 'admin.orders.index', 'admin.customers.index',
            'admin.delivery-drivers.index', 'admin.products.index', 'admin.combos.index',
            'admin.categories.index', 'admin.extras.index', 'admin.extra-categories.index',
            'admin.appearance.index', 'admin.settings.edit', 'admin.whatsapp.index',
            'admin.social-links.index', 'admin.reviews.google', 'admin.order-ratings.index',
            'storefront.index', 'combos.index', 'admin.logout',
        ] as $name) {
            $response->assertSee(route($name), false);
        }
        $response->assertSee('name="_token"', false);
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertMatchesRegularExpression(
            '/href="'.preg_quote(route('admin.customers.index'), '/').'"[^>]*aria-current="page"/',
            $response->getContent()
        );
        $this->assertSame('christmas', StoreSetting::current()->storefront_theme);
    }

    public function test_admin_login_uses_sage_without_changing_customer_login(): void
    {
        $this->get(route('admin.login'))->assertOk()->assertSee('data-admin-theme="sage"', false);
        $this->get(route('login'))->assertOk()->assertSee('data-theme="formal"', false)
            ->assertDontSee('data-admin-theme', false);
    }

    public function test_sage_preview_is_private_and_does_not_publish(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.appearance.index'))->assertOk()->assertSee('Previsualizar Salvia');
        $this->get(route('admin.appearance.preview', 'sage'))->assertOk()
            ->assertSee('data-theme="sage"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Content-Security-Policy', 'sandbox allow-same-origin');
        $this->assertSame('formal', StoreSetting::current()->storefront_theme);
        $this->get(route('storefront.index', ['theme' => 'sage']))->assertOk()
            ->assertSee('data-theme="formal"', false);
    }

    public function test_publishing_sage_and_restoring_another_theme_keeps_admin_sage(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.appearance.update'), ['theme' => 'sage'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('storefront.index'))->assertOk()->assertSee('data-theme="sage"', false)
            ->assertSee('property="og:title"', false)->assertHeaderMissing('X-Robots-Tag');
        $this->put(route('admin.appearance.update'), ['theme' => 'original'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('storefront.index'))->assertOk()->assertSee('data-theme="original"', false);
        $this->get(route('admin.appearance.index'))->assertOk()->assertSee('data-admin-theme="sage"', false);
    }

    public function test_sage_reaches_all_customer_document_roots(): void
    {
        StoreSetting::current()->forceFill(['storefront_theme' => 'sage'])->save();
        foreach (['storefront.index', 'combos.index', 'login', 'customer.register', 'cart.index'] as $name) {
            $this->get(route($name))->assertOk()->assertSee('data-theme="sage"', false);
        }
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('customer.account'))->assertOk()->assertSee('data-theme="sage"', false);
    }
}
