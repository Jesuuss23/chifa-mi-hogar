<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductRecommendation;
use App\Models\User;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class RecommendationImageEditingTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::create(['name' => 'Carta', 'slug' => 'carta', 'is_active' => true]);
        $this->product = Product::create([
            'category_id' => $category->id,
            'name' => 'Chaufa', 'slug' => 'chaufa', 'price' => 20,
        ]);
    }

    public function test_edit_without_upload_preserves_legacy_image(): void
    {
        $recommendation = $this->recommendation(['image_path' => 'recommendations/legacy.jpg']);
        $this->mock(ImageService::class)->shouldNotReceive('upload');

        $this->put($this->updateUrl($recommendation), $this->data())
            ->assertRedirect(route('admin.products.recommendations.index', $this->product))
            ->assertSessionHasNoErrors();

        $recommendation->refresh();
        $this->assertSame('Nombre actualizado', $recommendation->name);
        $this->assertSame('recommendations/legacy.jpg', $recommendation->image_path);
        $this->assertNull($recommendation->media_asset_id);
    }

    public function test_edit_without_upload_preserves_optimized_image(): void
    {
        $asset = $this->asset();
        $recommendation = $this->recommendation(['media_asset_id' => $asset->id]);
        $this->mock(ImageService::class)->shouldNotReceive('upload');

        $this->put($this->updateUrl($recommendation), $this->data())->assertSessionHasNoErrors();
        $this->assertSame('Nombre actualizado', $recommendation->fresh()->name);
        $this->assertEquals($asset->id, $recommendation->fresh()->media_asset_id);
    }

    public function test_new_recommendation_still_requires_uploaded_image(): void
    {
        $this->mock(ImageService::class)->shouldNotReceive('upload');
        // Sending a path or asset ID cannot bypass server-side upload validation.
        $this->post(route('admin.products.recommendations.store', $this->product), [
            ...$this->data(), 'image_path' => 'forged.jpg', 'media_asset_id' => 999,
        ])->assertSessionHasErrors('image');
        $this->assertDatabaseCount('product_recommendations', 0);
    }

    public function test_existing_recommendation_without_any_image_still_requires_upload(): void
    {
        $recommendation = $this->recommendation();
        $before = $recommendation->fresh()->getAttributes();
        $this->mock(ImageService::class)->shouldNotReceive('upload');

        $this->put($this->updateUrl($recommendation), [
            ...$this->data(), 'image_path' => 'forged.jpg', 'media_asset_id' => 999,
        ])->assertSessionHasErrors('image');
        $this->assertSame($before, $recommendation->fresh()->getAttributes());
    }

    public function test_invalid_replacement_is_rejected_without_changing_existing_image(): void
    {
        $recommendation = $this->recommendation(['image_path' => 'recommendations/legacy.jpg']);
        $before = $recommendation->fresh()->getAttributes();
        $this->mock(ImageService::class)->shouldNotReceive('upload');

        $this->put($this->updateUrl($recommendation), [
            ...$this->data(),
            'image' => UploadedFile::fake()->create('document.pdf', 10, 'application/pdf'),
        ])->assertSessionHasErrors('image');
        $this->assertSame($before, $recommendation->fresh()->getAttributes());
    }

    public function test_valid_upload_can_replace_legacy_image(): void
    {
        $recommendation = $this->recommendation(['image_path' => 'recommendations/legacy.jpg']);
        $asset = $this->asset();
        $this->mock(ImageService::class)->shouldReceive('upload')->once()->andReturn($asset);

        $this->put($this->updateUrl($recommendation), [
            ...$this->data(), 'image' => UploadedFile::fake()->image('replacement.jpg', 10, 10),
        ])->assertSessionHasNoErrors();

        $recommendation->refresh();
        $this->assertEquals($asset->id, $recommendation->media_asset_id);
        $this->assertSame('Nombre actualizado', $recommendation->name);
        // The existing renderer prefers media_asset over the legacy fallback.
        $this->assertSame('recommendations/legacy.jpg', $recommendation->image_path);
    }

    private function data(): array
    {
        return ['name' => 'Nombre actualizado', 'sort_order' => 0, 'is_active' => true];
    }

    private function updateUrl(ProductRecommendation $recommendation): string
    {
        return route('admin.products.recommendations.update', [$this->product, $recommendation]);
    }

    private function recommendation(array $overrides = []): ProductRecommendation
    {
        return ProductRecommendation::create(array_replace([
            'product_id' => $this->product->id,
            'name' => 'Original', 'slug' => 'original', 'is_active' => true,
        ], $overrides));
    }

    private function asset(): MediaAsset
    {
        return MediaAsset::create([
            'original_path' => 'originals/test.jpg',
            'thumbnail_path' => 'catalog/thumbnails/test.webp',
            'card_path' => 'catalog/cards/test.webp',
            'detail_path' => 'catalog/details/test.webp',
            'original_name' => 'test.jpg', 'mime_type' => 'image/jpeg',
            'original_size' => 100, 'width' => 10, 'height' => 10,
        ]);
    }
}
