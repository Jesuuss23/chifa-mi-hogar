<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SummerThemeTest extends TestCase
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

    public function test_preview_does_not_replace_the_saved_christmas_theme(): void
    {
        $settings = StoreSetting::current();
        $settings->forceFill(['storefront_theme' => 'christmas'])->save();
        $before = $settings->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.appearance.index'))->assertOk()->assertSee('Previsualizar Verano');
        $preview = $this->get(route('admin.appearance.preview', 'summer'))
            ->assertOk()->assertSee('data-theme="summer"', false)
            ->assertSee('class="summer-decor" aria-hidden="true"', false)
            ->assertSee('Sabor para tus días de verano')
            ->assertDontSee('christmas-cane.svg')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Content-Security-Policy', 'sandbox allow-same-origin');
        $this->assertTrue($preview->headers->hasCacheControlDirective('no-store'));
        $this->assertSame($before, $settings->fresh()->getAttributes());
        $this->get(route('storefront.index', ['theme' => 'summer']))
            ->assertOk()->assertSee('data-theme="christmas"', false)
            ->assertDontSee('class="summer-decor"', false);
    }

    public function test_admin_can_apply_summer_then_restore_formal(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.appearance.update'), ['theme' => 'summer'])
            ->assertRedirect(route('admin.appearance.index'))->assertSessionHasNoErrors();
        $this->assertSame('summer', StoreSetting::current()->storefront_theme);
        $this->get(route('storefront.index'))->assertOk()
            ->assertSee('data-theme="summer"', false)
            ->assertSee('class="summer-sun"', false)->assertSee('class="summer-waves"', false)
            ->assertSee('name="theme-color" content="#086b70"', false)
            ->assertSee('property="og:title"', false)
            ->assertHeaderMissing('X-Robots-Tag');
        $this->put(route('admin.appearance.update'), ['theme' => 'formal'])->assertRedirect();
        $this->get(route('storefront.index'))->assertOk()
            ->assertSee('data-theme="formal"', false)->assertDontSee('class="summer-decor"', false);
    }

    public function test_palette_reaches_customer_documents_without_hero_ornaments(): void
    {
        StoreSetting::current()->forceFill(['storefront_theme' => 'summer'])->save();
        foreach (['login', 'customer.register', 'cart.index', 'combos.index'] as $name) {
            $this->get(route($name))->assertOk()->assertSee('data-theme="summer"', false)
                ->assertDontSee('class="summer-decor"', false);
        }
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('customer.account'))->assertOk()->assertSee('data-theme="summer"', false)
            ->assertDontSee('class="summer-decor"', false);
    }

    public static function restrictedRoles(): array
    {
        return [['customer'], ['delivery']];
    }

    #[DataProvider('restrictedRoles')]
    public function test_other_roles_cannot_preview_or_publish_summer(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route('admin.appearance.preview', 'summer'))->assertForbidden();
        $this->put(route('admin.appearance.update'), ['theme' => 'summer'])->assertForbidden();
        $this->assertSame('formal', StoreSetting::current()->storefront_theme);
    }

    public function test_admin_portal_keeps_its_existing_appearance(): void
    {
        StoreSetting::current()->forceFill(['storefront_theme' => 'summer'])->save();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.appearance.index'))->assertOk()
            ->assertDontSee('data-theme="summer"', false)
            ->assertDontSee('class="summer-decor"', false);
    }
}
