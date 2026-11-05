<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use App\Services\BrandShareImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicationOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    private function completeFields(): array
    {
        return [
            'business_owner' => 'Titular de prueba',
            'privacy_email' => 'contact@example.com',
            'website_url' => 'https://example.com',
            'legal_address' => 'Domicilio de prueba',
            'privacy_hosting' => 'Alojamiento de prueba',
            'privacy_retention' => 'Plazos de prueba',
            'privacy_database' => 'Banco de datos de prueba',
        ];
    }

    public static function requiredFields(): array
    {
        return array_map(fn ($field) => [$field], [
            'business_owner', 'privacy_email', 'website_url', 'legal_address',
            'privacy_hosting', 'privacy_retention', 'privacy_database',
        ]);
    }

    #[DataProvider('requiredFields')]
    public function test_missing_field_list_matches_public_availability(string $field): void
    {
        $settings = new StoreSetting($this->completeFields() + ['legal_pages_published' => true]);
        $this->assertSame([], $settings->missingLegalFields());
        $this->assertTrue($settings->legalPagesAvailable());
        $settings->$field = '   ';
        $this->assertSame([$field], array_keys($settings->missingLegalFields()));
        $this->assertFalse($settings->legalPagesAvailable());
    }

    public static function publicationStates(): array
    {
        return [
            'incomplete' => [false, false, 'Páginas informativas no disponibles: faltan datos guardados'],
            'complete draft' => [true, false, 'Borrador completo, pendiente de publicación'],
            'published' => [true, true, 'Páginas informativas publicadas'],
        ];
    }

    #[DataProvider('publicationStates')]
    public function test_admin_overview_reflects_saved_state_without_publishing(bool $complete, bool $published, string $message): void
    {
        $settings = StoreSetting::current();
        if ($complete) {
            $settings->fill($this->completeFields());
        }
        $settings->legal_pages_published = $published;
        $settings->save();
        $before = $settings->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $response = $this->get(route('admin.settings.edit'))->assertOk()->assertSee($message);
        if (! $complete) {
            $response->assertSee('href="#legal_address"', false);
        }
        $this->assertSame($before, $settings->fresh()->getAttributes());
        Http::assertNothingSent();
    }

    public function test_preview_link_and_image_use_the_current_logo_version(): void
    {
        $this->mock(BrandShareImageService::class, function ($mock) {
            $mock->shouldReceive('source')->twice()->andReturn(null);
            $mock->shouldReceive('version')->with(null)->twice()->andReturn('first-version', 'second-version');
        });
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach (['first-version', 'second-version'] as $version) {
            $url = route('brand.share-image', ['v' => $version]);
            $this->get(route('admin.settings.edit'))->assertOk()
                ->assertSee('href="'.$url.'"', false)
                ->assertSee('src="'.$url.'"', false);
        }
        Http::assertNothingSent();
    }

    #[DataProvider('restrictedRoles')]
    public function test_non_admin_cannot_read_overview(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('admin.settings.edit'))->assertForbidden();
    }

    public static function restrictedRoles(): array
    {
        return [['customer'], ['delivery']];
    }

    public function test_guest_cannot_read_overview(): void
    {
        $this->get(route('admin.settings.edit'))->assertRedirect();
    }
}
