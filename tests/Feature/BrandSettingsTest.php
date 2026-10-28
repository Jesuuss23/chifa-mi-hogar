<?php

namespace Tests\Feature;

use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BrandSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        Storage::fake('local');
        Storage::fake('public');
        Http::preventStrayRequests();
    }

    private function data(array $overrides = []): array
    {
        return array_replace([
            'business_owner' => 'Rossi Rivadeneyra Jara',
            'privacy_email' => 'chifamihogar@gmail.com',
            'website_url' => 'https://chifamihogar.com',
            'business_ruc' => null,
        ], $overrides);
    }

    private function admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    public function test_brand_form_has_initial_business_details_and_pending_ruc(): void
    {
        $this->admin();
        $this->get(route('admin.settings.edit'))->assertOk()
            ->assertSee('Identidad y datos del negocio')
            ->assertSee('Rossi Rivadeneyra Jara')
            ->assertSee('chifamihogar@gmail.com')
            ->assertSee('https://chifamihogar.com');
        $this->assertNull(StoreSetting::current()->fresh()->business_ruc);
    }

    public function test_admin_can_upload_logo_without_changing_payment_or_delivery_settings(): void
    {
        $this->admin();
        $settings = StoreSetting::current();
        $settings->update([
            'payment_holder' => 'Titular de pagos',
            'free_delivery_km' => 5,
            'accepting_orders' => false,
        ]);

        $this->put(route('admin.settings.brand.update'), $this->data([
            'logo' => UploadedFile::fake()->image('logo.jpeg', 100, 100),
        ]))->assertRedirect(route('admin.settings.edit'))->assertSessionHasNoErrors();

        $settings->refresh();
        $this->assertNotNull($settings->logo);
        $this->assertSame('logo.jpeg', $settings->logo->original_name);
        Storage::disk('local')->assertExists($settings->logo->original_path);
        Storage::disk('public')->assertExists($settings->logo->thumbnail_path);
        $this->assertSame('Titular de pagos', $settings->payment_holder);
        $this->assertSame('5.00', $settings->free_delivery_km);
        $this->assertFalse($settings->accepting_orders);

        $this->get(route('login'))->assertOk()
            ->assertSee($settings->logo->thumbnailUrl(), false);
        Http::assertNothingSent();
    }

    public function test_saving_details_without_upload_preserves_existing_logo(): void
    {
        $this->admin();
        $this->put(route('admin.settings.brand.update'), $this->data([
            'logo' => UploadedFile::fake()->image('logo.jpeg', 50, 50),
        ]))->assertSessionHasNoErrors();
        $id = StoreSetting::current()->logo_media_asset_id;

        $this->put(route('admin.settings.brand.update'), $this->data([
            'business_owner' => 'Titular actualizado',
            'business_ruc' => '20123456789',
        ]))->assertSessionHasNoErrors();
        $settings = StoreSetting::current()->fresh();
        $this->assertSame($id, $settings->logo_media_asset_id);
        $this->assertSame('Titular actualizado', $settings->business_owner);
        $this->assertSame('20123456789', $settings->business_ruc);
        $this->assertDatabaseCount('media_assets', 1);
    }

    public function test_replacement_uses_new_logo_and_preserves_previous_asset(): void
    {
        $this->admin();
        $this->put(route('admin.settings.brand.update'), $this->data([
            'logo' => UploadedFile::fake()->image('old.jpeg', 50, 50),
        ]))->assertSessionHasNoErrors();
        $old = StoreSetting::current()->logo;

        $this->put(route('admin.settings.brand.update'), $this->data([
            'logo' => UploadedFile::fake()->image('logo.jpeg', 60, 60),
        ]))->assertSessionHasNoErrors();
        $new = StoreSetting::current()->logo;
        $this->assertNotSame($old->id, $new->id);
        $this->assertSame($new->thumbnailUrl(), StoreSetting::current()->logoUrl());
        Storage::disk('public')->assertExists($old->thumbnail_path);
        Storage::disk('public')->assertExists($new->thumbnail_path);
    }

    public static function invalidFields(): array
    {
        return [
            'ruc placeholder' => ['business_ruc', '11111111.....'],
            'email' => ['privacy_email', 'not-an-email'],
            'unsafe url' => ['website_url', 'javascript:alert(1)'],
        ];
    }

    #[DataProvider('invalidFields')]
    public function test_invalid_business_details_are_rejected(string $field, string $value): void
    {
        $this->admin();
        $settings = StoreSetting::current()->fresh();
        $before = $settings->getAttributes();
        $this->put(route('admin.settings.brand.update'), $this->data([$field => $value]))
            ->assertSessionHasErrors($field);
        $this->assertSame($before, $settings->fresh()->getAttributes());
    }

    public function test_disguised_file_and_oversized_logo_are_rejected_without_replacing_logo(): void
    {
        $this->admin();
        $this->put(route('admin.settings.brand.update'), $this->data([
            'logo' => UploadedFile::fake()->createWithContent('logo.jpeg', '<?php echo "unsafe";'),
        ]))->assertSessionHasErrors('logo');
        $this->put(route('admin.settings.brand.update'), $this->data([
            'logo' => UploadedFile::fake()->image('logo.jpeg')->size(2049),
        ]))->assertSessionHasErrors('logo');
        $this->assertDatabaseCount('media_assets', 0);
        $this->assertNull(StoreSetting::current()->logo_media_asset_id);
    }

    public static function unauthorizedRoles(): array
    {
        return ['customer' => ['customer'], 'delivery' => ['delivery']];
    }

    #[DataProvider('unauthorizedRoles')]
    public function test_non_admin_cannot_update_brand(string $role): void
    {
        $this->actingAs(User::factory()->create(['role' => $role]));
        $this->put(route('admin.settings.brand.update'), $this->data())->assertForbidden();
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_guest_cannot_update_brand(): void
    {
        $this->put(route('admin.settings.brand.update'), $this->data())->assertRedirect();
        $this->assertDatabaseCount('media_assets', 0);
    }
}
