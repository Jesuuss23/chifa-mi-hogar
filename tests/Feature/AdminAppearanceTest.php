<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminAppearanceTest extends TestCase
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

    public function test_formal_remains_the_default_and_unknown_saved_values_fall_back(): void
    {
        $settings = StoreSetting::current();
        $this->assertSame('formal', $settings->fresh()->storefront_theme);
        $this->get(route('storefront.index'))->assertOk()->assertSee('data-theme="formal"', false);
        $settings->forceFill(['storefront_theme' => 'unknown'])->save();
        $this->get(route('storefront.index'))->assertOk()->assertSee('data-theme="formal"', false);
    }

    public function test_guests_cannot_view_preview_or_publish(): void
    {
        $this->get(route('admin.appearance.index'))->assertRedirect();
        $this->get(route('admin.appearance.preview', 'original'))->assertRedirect();
        $this->put(route('admin.appearance.update'), ['theme' => 'original'])->assertRedirect();
        $this->assertSame('formal', StoreSetting::current()->storefront_theme);
    }

    public static function nonAdmins(): array
    {
        return [['customer'], ['delivery']];
    }

    #[DataProvider('nonAdmins')]
    public function test_non_admin_roles_cannot_view_preview_or_publish(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route('admin.appearance.index'))->assertForbidden();
        $this->get(route('admin.appearance.preview', 'original'))->assertForbidden();
        $this->put(route('admin.appearance.update'), ['theme' => 'original'])->assertForbidden();
        $this->assertSame('formal', StoreSetting::current()->storefront_theme);
    }

    public function test_preview_is_private_non_interactive_and_does_not_change_public_theme(): void
    {
        $settings = StoreSetting::current();
        $before = $settings->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->get(route('admin.appearance.index'))->assertOk()
            ->assertSee('Previsualizar Formal')->assertSee('Previsualizar Original');
        $response = $this->get(route('admin.appearance.preview', 'original'))
            ->assertOk()->assertSee('data-theme="original"', false)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertHeader('Content-Security-Policy', 'sandbox allow-same-origin');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertMatchesRegularExpression('/<body\\s+inert\\s/', $response->getContent());
        $this->assertSame($before, $settings->fresh()->getAttributes());
        $this->get(route('storefront.index', ['theme' => 'original']))
            ->assertOk()->assertSee('data-theme="formal"', false)
            ->assertHeaderMissing('X-Robots-Tag');
        $this->get(route('admin.appearance.preview', 'unknown'))->assertNotFound();
    }

    public function test_apply_only_changes_theme_and_can_restore_formal(): void
    {
        $settings = StoreSetting::current();
        $before = $settings->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.appearance.update'), [
            'theme' => 'original', 'business_owner' => 'Changed', 'accepting_orders' => false,
        ])->assertRedirect(route('admin.appearance.index'))->assertSessionHasNoErrors();
        $after = $settings->fresh()->getAttributes();
        $this->assertSame('original', $after['storefront_theme']);
        unset($before['storefront_theme'], $before['updated_at'], $after['storefront_theme'], $after['updated_at']);
        $this->assertSame($before, $after);
        $this->get(route('storefront.index'))->assertOk()->assertSee('data-theme="original"', false);
        $this->put(route('admin.appearance.update'), ['theme' => 'formal'])->assertRedirect();
        $this->assertSame('formal', $settings->fresh()->storefront_theme);
    }

    public static function invalidThemes(): array
    {
        return [[[]], [['theme' => 'unknown-season']], [['theme' => ['formal']]], [['theme' => '<script>']]];
    }

    #[DataProvider('invalidThemes')]
    public function test_invalid_themes_are_rejected(array $input): void
    {
        $settings = StoreSetting::current();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('admin.appearance.index'))
            ->put(route('admin.appearance.update'), $input)->assertSessionHasErrors('theme');
        $this->assertSame('formal', $settings->fresh()->storefront_theme);
    }

    public function test_saved_theme_reaches_each_customer_document_and_ignores_query_override(): void
    {
        StoreSetting::current()->forceFill(['storefront_theme' => 'original'])->save();
        foreach (['storefront.index', 'combos.index', 'login', 'customer.register', 'cart.index'] as $route) {
            $this->get(route($route, ['theme' => 'formal', 'preview' => 'formal']))
                ->assertOk()->assertSee('data-theme="original"', false);
        }
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->get(route('customer.account'))->assertOk()->assertSee('data-theme="original"', false);
    }
}
