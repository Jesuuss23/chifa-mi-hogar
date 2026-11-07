<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ChristmasThemeTest extends TestCase
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

    public function test_admin_can_preview_christmas_without_publishing_it(): void
    {
        $settings = StoreSetting::current();
        $before = $settings->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.appearance.index'))->assertOk()->assertSee('Previsualizar Navidad');
        $response = $this->get(route('admin.appearance.preview', 'christmas'))
            ->assertOk()->assertSee('data-theme="christmas"', false)
            ->assertSee('Una Navidad para compartir')
            ->assertSee('christmas-cane.svg')
            ->assertDontSee('class="christmas-snow-pause"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Content-Security-Policy', 'sandbox allow-same-origin');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertSame($before, $settings->fresh()->getAttributes());
        $this->get(route('storefront.index', ['theme' => 'christmas']))
            ->assertOk()->assertSee('data-theme="formal"', false)
            ->assertDontSee('christmas-cane.svg');
    }

    public function test_admin_can_publish_christmas_and_restore_formal(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.appearance.update'), ['theme' => 'christmas'])
            ->assertRedirect(route('admin.appearance.index'))->assertSessionHasNoErrors();
        $this->assertSame('christmas', StoreSetting::current()->storefront_theme);
        $this->get(route('storefront.index'))->assertOk()
            ->assertSee('data-theme="christmas"', false)
            ->assertSee('christmas-cane.svg')->assertSee('christmas-hat.svg')
            ->assertSee('Pausar nieve')
            ->assertSee('name="theme-color" content="#103f33"', false)
            ->assertSee('property="og:title"', false)
            ->assertHeaderMissing('X-Robots-Tag');
        $this->put(route('admin.appearance.update'), ['theme' => 'formal'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->get(route('storefront.index'))->assertOk()
            ->assertSee('data-theme="formal"', false)->assertDontSee('christmas-cane.svg');
    }

    public function test_christmas_reaches_customer_pages_but_snow_stays_out_of_forms(): void
    {
        StoreSetting::current()->forceFill(['storefront_theme' => 'christmas'])->save();
        foreach (['login', 'customer.register', 'cart.index', 'combos.index'] as $name) {
            $this->get(route($name))->assertOk()->assertSee('data-theme="christmas"', false)
                ->assertDontSee('class="christmas-snow"', false)
                ->assertDontSee('christmas-cane.svg');
        }
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('customer.account'))->assertOk()
            ->assertSee('data-theme="christmas"', false)
            ->assertDontSee('class="christmas-snow"', false);
    }

    public function test_christmas_selection_does_not_change_admin_portal_theme(): void
    {
        StoreSetting::current()->forceFill(['storefront_theme' => 'christmas'])->save();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.appearance.index'))->assertOk()
            ->assertDontSee('data-theme="christmas"', false)
            ->assertDontSee('class="christmas-snow"', false);
    }
}
