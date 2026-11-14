<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Extra;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\PromotionalBanner;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PromotionalBannerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array']);
        Storage::fake('public');
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Carta', 'slug' => uniqid('c-'), 'is_active' => true]);

        return Product::create(['name' => 'Chaufa', 'slug' => uniqid('p-'), 'price' => 20,
            'category_id' => $category->id, 'is_available' => true]);
    }

    private function image(): MediaAsset
    {
        $path = 'catalog/'.uniqid().'.webp';
        Storage::disk('public')->put($path, 'fixture');

        return MediaAsset::create(['original_path' => 'originals/test.jpg', 'original_name' => 'test.jpg',
            'thumbnail_path' => $path, 'card_path' => $path, 'detail_path' => $path,
            'mime_type' => 'image/jpeg', 'original_size' => 100, 'width' => 1600, 'height' => 600]);
    }

    private function banner(array $attributes = []): PromotionalBanner
    {
        return PromotionalBanner::create(array_replace([
            'title' => 'Promoción destacada', 'product_id' => $this->product()->id,
            'media_asset_id' => $this->image()->id, 'is_active' => true, 'sort_order' => 0,
        ], $attributes));
    }

    public function test_admin_can_upload_preview_edit_retarget_and_delete_banner(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        $this->get(route('admin.banners.create'))->assertOk()->assertSee('data-admin-image-preview', false);
        $this->post(route('admin.banners.store'), [
            'title' => 'Oferta de hoy', 'target_type' => 'product', 'product_id' => $product->id,
            'sort_order' => 2, 'is_active' => 1,
            'image' => UploadedFile::fake()->image('banner.jpg', 800, 300),
            'mobile_image' => UploadedFile::fake()->image('mobile.png', 900, 600),
        ])->assertRedirect(route('admin.banners.index'));
        $banner = PromotionalBanner::firstOrFail();
        Storage::disk('public')->assertExists($banner->mediaAsset->detail_path);
        Storage::disk('local')->assertExists($banner->mediaAsset->original_path);
        $this->assertNotNull($banner->mobile_media_asset_id);
        $originalAsset = $banner->media_asset_id;
        $this->get(route('admin.banners.index'))->assertOk()->assertSee('Oferta de hoy');
        $this->get(route('admin.banners.edit', $banner))->assertOk();
        $combo = Combo::create(['name' => 'Dúo', 'slug' => 'duo', 'price' => 35, 'is_available' => true, 'show_in_menu' => true]);
        $this->put(route('admin.banners.update', $banner), [
            'title' => 'Dúo en oferta', 'target_type' => 'combo', 'combo_id' => $combo->id,
            'product_id' => $product->id, 'sort_order' => 1, 'is_active' => 1, 'remove_mobile_image' => 1,
        ])->assertRedirect();
        $banner->refresh();
        $this->assertNull($banner->product_id);
        $this->assertSame($combo->id, $banner->combo_id);
        $this->assertNull($banner->mobile_media_asset_id);
        $this->assertSame($originalAsset, $banner->media_asset_id);
        $this->assertSame(route('combos.show', $combo->slug), $banner->destinationUrl());
        $this->delete(route('admin.banners.destroy', $banner))->assertRedirect();
        $this->assertDatabaseMissing('promotional_banners', ['id' => $banner->id]);
    }

    public function test_banner_target_is_exclusive_in_server_rendered_form(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $html = $this->get(route('admin.banners.create'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-banner-destination="combo"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/id="banner-combo"[^>]*disabled/', $html);
        $combo = Combo::create(['name' => 'Dúo', 'slug' => 'edit-duo', 'price' => 20]);
        $banner = $this->banner(['product_id' => null, 'combo_id' => $combo->id]);
        $html = $this->get(route('admin.banners.edit', $banner))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-banner-destination="product"\s+hidden/', $html);
        $this->assertMatchesRegularExpression('/id="banner-product"[^>]*disabled/', $html);
    }

    public function test_new_banner_images_reject_portrait_oversized_and_tiny_dimensions(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        $data = ['title' => 'Promoción', 'target_type' => 'product', 'product_id' => $product->id, 'sort_order' => 1, 'is_active' => 1];
        foreach ([[400, 200], [800, 1200], [3300, 1000], [1600, 200]] as [$width, $height]) {
            $this->post(route('admin.banners.store'), $data + ['image' => UploadedFile::fake()->image('banner.jpg', $width, $height)])
                ->assertSessionHasErrors('image');
        }
        $this->post(route('admin.banners.store'), $data + [
            'image' => UploadedFile::fake()->image('banner.jpg', 1600, 500),
            'mobile_image' => UploadedFile::fake()->image('mobile.jpg', 900, 1100),
        ])->assertSessionHasErrors('mobile_image');
        $this->assertDatabaseCount('promotional_banners', 0);
        $this->assertDatabaseCount('media_assets', 0);
    }

    public function test_guests_customers_and_delivery_cannot_manage_banners(): void
    {
        $banner = $this->banner();
        $this->get(route('admin.banners.index'))->assertRedirect();
        foreach (['customer', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.banners.index'))->assertForbidden();
            $this->get(route('admin.banners.create'))->assertForbidden();
            $this->get(route('admin.banners.edit', $banner))->assertForbidden();
            $this->post(route('admin.banners.store'), [])->assertForbidden();
            $this->put(route('admin.banners.update', $banner), [])->assertForbidden();
            $this->delete(route('admin.banners.destroy', $banner))->assertForbidden();
        }
        $this->assertDatabaseHas('promotional_banners', ['id' => $banner->id]);
    }

    public function test_upload_and_destination_validation_rejects_invalid_or_deleted_targets(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $product = $this->product();
        $product->delete();
        $this->post(route('admin.banners.store'), [
            'title' => 'Invalid', 'target_type' => 'product', 'product_id' => $product->id,
            'sort_order' => -1, 'is_active' => 1,
            'image' => UploadedFile::fake()->create('bad.svg', 1, 'image/svg+xml'),
        ])->assertSessionHasErrors(['product_id', 'sort_order', 'image']);
        $this->post(route('admin.banners.store'), ['title' => 'Invalid', 'target_type' => 'url',
            'sort_order' => 1, 'is_active' => 1])->assertSessionHasErrors(['target_type', 'image']);
        $this->assertDatabaseCount('promotional_banners', 0);
    }

    public function test_public_carousel_orders_valid_banners_and_escapes_title(): void
    {
        $last = $this->banner(['sort_order' => 10]);
        $first = $this->banner(['sort_order' => 1, 'title' => '<script>bad()</script>', 'mobile_media_asset_id' => $this->image()->id]);
        $this->banner(['is_active' => false]);
        $this->get(route('storefront.index'))->assertOk()
            ->assertViewHas('banners', fn ($banners) => $banners->modelKeys() === [$first->id, $last->id])
            ->assertSee('data-promo-carousel', false)->assertSee('data-promo-index="1"', false)
            ->assertSee('media="(max-width: 639px)"', false)
            ->assertSee('href="'.route('storefront.show', $first->product->slug).'"', false)
            ->assertDontSee('<script>bad()</script>', false)->assertSee('&lt;script&gt;bad()&lt;/script&gt;', false);
    }

    public function test_missing_image_disabled_category_deleted_target_and_out_of_date_combo_are_hidden(): void
    {
        $missing = $this->banner();
        Storage::disk('public')->delete($missing->mediaAsset->detail_path);
        $disabled = $this->banner();
        $disabled->product->category->update(['is_active' => false]);
        $deleted = $this->banner();
        $deleted->product->delete();
        $unavailable = $this->banner();
        $unavailable->product->update(['is_available' => false]);
        $combo = Combo::create(['name' => 'Dúo', 'slug' => 'future', 'price' => 30, 'show_in_menu' => true,
            'is_available' => true, 'availability_mode' => 'dates', 'available_dates' => ['2000-01-01']]);
        $this->banner(['product_id' => null, 'combo_id' => $combo->id]);
        $this->get(route('storefront.index'))->assertOk()->assertViewHas('banners', fn ($banners) => $banners->isEmpty())
            ->assertDontSee('data-promo-carousel', false)->assertSee('El sabor que', false);
        $combo->update(['availability_mode' => 'always']);
        $this->get(route('storefront.index'))->assertOk()->assertViewHas('banners', fn ($banners) => $banners->count() === 1);
        $combo->update(['show_in_menu' => false]);
        $this->get(route('storefront.index'))->assertOk()->assertViewHas('banners', fn ($banners) => $banners->isEmpty());
    }

    public function test_product_extras_and_fixed_combo_components_have_images_and_quantity_buttons(): void
    {
        $product = $this->product();
        $product->update(['media_asset_id' => $this->image()->id]);
        $extra = Extra::create(['name' => 'Gaseosa', 'price' => 5, 'is_available' => true, 'media_asset_id' => $this->image()->id]);
        $product->extras()->attach($extra);
        $combo = Combo::create(['name' => 'Dúo', 'slug' => 'duo-photos', 'price' => 35, 'is_available' => true, 'show_in_menu' => true]);
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 2]);
        $combo->items()->create(['extra_id' => $extra->id, 'quantity' => 2]);
        $this->get(route('storefront.show', $product->slug))->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url($extra->mediaAsset->thumbnail_path).'"', false);
        $this->get(route('combos.show', $combo->slug))->assertOk()
            ->assertSee('src="'.Storage::disk('public')->url($product->mediaAsset->thumbnail_path).'"', false)
            ->assertSee('src="'.Storage::disk('public')->url($extra->mediaAsset->thumbnail_path).'"', false)
            ->assertSee('data-quantity-step="-1"', false)->assertSee('data-quantity-step="1"', false)
            ->assertDontSee('type="number"', false)->assertDontSee('name="components[', false);
        Storage::disk('public')->delete($extra->mediaAsset->thumbnail_path);
        $this->get(route('combos.show', $combo->slug))->assertOk()->assertSee('Sin imagen');
    }
}
