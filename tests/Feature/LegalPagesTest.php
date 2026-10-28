<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Http::preventStrayRequests();
    }

    private function fields(array $overrides = []): array
    {
        return array_replace([
            'legal_pages_published' => '1',
            'legal_address' => 'Dirección de prueba, Lima',
            'privacy_hosting' => 'Proveedor de prueba y país confirmado por el negocio.',
            'privacy_retention' => 'Plazos de prueba, no una política real de producción.',
            'privacy_database' => 'Banco de datos de prueba, titular identificado.',
        ], $overrides);
    }

    private function publish(): StoreSetting
    {
        $settings = StoreSetting::current();
        $settings->update($this->fields());
        return $settings;
    }

    public static function pages(): array
    {
        return ['notice' => ['notice'], 'privacy' => ['privacy'], 'cookies' => ['cookies']];
    }

    #[DataProvider('pages')]
    public function test_drafts_are_not_public(string $page): void
    {
        $this->get(route('legal.'.$page))->assertNotFound();
        Http::assertNothingSent();
    }

    #[DataProvider('pages')]
    public function test_admin_can_preview_drafts_without_indexing(string $page): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->get(route('admin.legal.preview', $page));
        $response->assertOk()
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
            ->assertSee('Vista previa para administración');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        Http::assertNothingSent();
    }

    #[DataProvider('pages')]
    public function test_published_pages_are_available_to_guests(string $page): void
    {
        $this->publish();
        $this->get(route('legal.'.$page))->assertOk()
            ->assertSee('chifamihogar@gmail.com')
            ->assertDontSee('Pendiente de completar en el borrador.')
            ->assertDontSee('Vista previa para administración');
        Http::assertNothingSent();
    }

    public function test_admin_can_save_draft_but_cannot_publish_incomplete_details(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->put(route('admin.settings.legal.update'), ['legal_pages_published' => '0'])
            ->assertRedirect(route('admin.settings.edit'))->assertSessionHasNoErrors();
        $this->assertFalse(StoreSetting::current()->legalPagesAvailable());

        $this->put(route('admin.settings.legal.update'), ['legal_pages_published' => '1'])
            ->assertSessionHasErrors(['legal_address', 'privacy_hosting', 'privacy_retention', 'privacy_database']);
        $this->assertFalse(StoreSetting::current()->legalPagesAvailable());
    }

    public function test_publish_and_unpublish_preserve_brand_payment_and_delivery(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $settings = StoreSetting::current();
        $settings->update(['free_delivery_km' => 7, 'payment_holder' => 'Titular de pagos']);
        $this->put(route('admin.settings.legal.update'), $this->fields())
            ->assertRedirect(route('admin.settings.edit'))->assertSessionHasNoErrors();
        $settings->refresh();
        $this->assertTrue($settings->legalPagesAvailable());
        $this->assertSame('7.00', $settings->free_delivery_km);
        $this->assertSame('Titular de pagos', $settings->payment_holder);
        $this->assertSame('Rossi Rivadeneyra Jara', $settings->business_owner);

        $this->put(route('admin.settings.legal.update'), $this->fields(['legal_pages_published' => '0']))
            ->assertSessionHasNoErrors();
        $this->get(route('legal.privacy'))->assertNotFound();
    }

    public static function restrictedRoles(): array
    {
        return ['customer' => ['customer'], 'delivery' => ['delivery']];
    }

    #[DataProvider('restrictedRoles')]
    public function test_only_admin_can_preview_or_change_legal_settings(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->get(route('admin.legal.preview', 'privacy'))->assertForbidden();
        $this->put(route('admin.settings.legal.update'), $this->fields())->assertForbidden();
        $this->assertFalse(StoreSetting::current()->legalPagesAvailable());
    }

    public function test_guest_cannot_preview_or_publish(): void
    {
        $this->get(route('admin.legal.preview', 'privacy'))->assertRedirect();
        $this->put(route('admin.settings.legal.update'), $this->fields())->assertRedirect();
        $this->assertFalse(StoreSetting::current()->legalPagesAvailable());
    }

    public function test_public_cookie_inventory_uses_configured_name_and_lifetime(): void
    {
        $this->publish();
        config(['session.cookie' => 'custom-session', 'session.lifetime' => 45]);
        $this->get(route('legal.cookies'))->assertOk()
            ->assertSee('custom-session')->assertSee('45 minutos')
            ->assertSee('XSRF-TOKEN')
            ->assertCookie('custom-session')
            ->assertCookie('XSRF-TOKEN');
        config(['session.expire_on_close' => true]);
        $this->get(route('legal.cookies'))->assertOk()->assertSee('Hasta cerrar el navegador');
    }

    public function test_legal_data_is_escaped_and_no_fake_ruc_is_published(): void
    {
        $settings = $this->publish();
        $settings->update([
            'legal_address' => '<script>alert(1)</script>',
            'privacy_retention' => '<img src=x onerror=alert(1)>',
            'business_ruc' => null,
        ]);
        $this->get(route('legal.privacy'))->assertOk()
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<img src=x onerror=alert(1)>', false)
            ->assertDontSee('11111111')
            ->assertDontSee('RUC pendiente');
    }

    public function test_turnstile_disclosure_follows_configuration_without_loading_widget(): void
    {
        $this->publish();
        config(['turnstile.enabled' => false]);
        $this->get(route('legal.privacy'))->assertOk()->assertDontSee('Cloudflare Turnstile');
        config(['turnstile.enabled' => true]);
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Cloudflare Turnstile')
            ->assertDontSee('https://challenges.cloudflare.com/turnstile/v0/api.js', false);
        Http::assertNothingSent();
    }

    public function test_footer_and_collection_links_only_appear_when_published(): void
    {
        $this->get(route('storefront.index'))->assertOk()->assertDontSee(route('legal.privacy'), false);
        $this->get(route('customer.register'))->assertOk()->assertDontSee(route('legal.privacy'), false);
        $this->publish();
        $this->get(route('storefront.index'))->assertOk()->assertSee(route('legal.privacy'), false);
        $this->get(route('customer.register'))->assertOk()->assertSee(route('legal.privacy'), false);
    }

    public function test_missing_required_detail_hides_even_previously_published_pages(): void
    {
        $settings = $this->publish();
        $settings->update(['legal_address' => null]);
        $this->get(route('legal.privacy'))->assertNotFound();
    }
}
