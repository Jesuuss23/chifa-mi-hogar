<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductRecommendation;
use App\Services\CatalogImageService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogImageCompatibilityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('local');
        Http::preventStrayRequests();
    }

    private function item(?string $legacy = null, ?MediaAsset $media = null): Product
    {
        $product = new Product(['name' => 'Chaufa', 'image_path' => $legacy]);
        $product->setRelation('mediaAsset', $media);
        return $product;
    }

    private function resolve(Model $item, string $variant = 'card'): ?string
    {
        return app(CatalogImageService::class)->url($item, $variant);
    }

    public static function variants(): array
    {
        return [['thumbnail'], ['card'], ['detail']];
    }

    #[DataProvider('variants')]
    public function test_requested_optimized_variant_has_priority(string $variant): void
    {
        $media = new MediaAsset([
            'thumbnail_path' => 'catalog/thumb.webp', 'card_path' => 'catalog/card.webp',
            'detail_path' => 'catalog/detail.webp',
        ]);
        foreach (['catalog/thumb.webp', 'catalog/card.webp', 'catalog/detail.webp', 'legacy.jpg'] as $path) {
            Storage::disk('public')->put($path, 'fixture');
        }
        $this->assertSame(Storage::disk('public')->url($media->getAttribute($variant.'_path')),
            $this->resolve($this->item('legacy.jpg', $media), $variant));
    }

    public function test_missing_requested_variant_uses_another_optimized_size(): void
    {
        Storage::disk('public')->put('catalog/detail.webp', 'fixture');
        Storage::disk('public')->put('legacy.jpg', 'fixture');
        $media = new MediaAsset(['card_path' => 'catalog/missing.webp', 'detail_path' => 'catalog/detail.webp']);
        $this->assertSame(Storage::disk('public')->url('catalog/detail.webp'),
            $this->resolve($this->item('legacy.jpg', $media)));
    }

    public function test_missing_optimized_files_fall_back_to_existing_legacy_file_without_mutation(): void
    {
        Storage::disk('public')->put('legacy.jpg', 'fixture');
        $item = $this->item('legacy.jpg', new MediaAsset(['card_path' => 'missing.webp']));
        $before = $item->getAttributes();
        $this->assertSame(Storage::disk('public')->url('legacy.jpg'), $this->resolve($item));
        $this->assertSame($before, $item->getAttributes());
        Storage::disk('public')->assertExists('legacy.jpg');
    }

    public static function legacyPaths(): array
    {
        return [
            ['products/old.jpg'], ['storage/products/old.jpg'], ['/storage/products/old.jpg'],
            ['products\\old.jpg'],
        ];
    }

    #[DataProvider('legacyPaths')]
    public function test_legacy_prefixes_resolve_without_duplicate_storage(string $path): void
    {
        Storage::disk('public')->put('products/old.jpg', 'fixture');
        $this->assertSame(Storage::disk('public')->url('products/old.jpg'),
            $this->resolve($this->item($path)));
    }

    public static function catalogModels(): array
    {
        return [[Category::class], [Product::class], [Extra::class],
            [ExtraCategory::class], [ProductRecommendation::class], [Combo::class]];
    }

    #[DataProvider('catalogModels')]
    public function test_shared_component_supports_catalog_models(string $model): void
    {
        Storage::disk('public')->put('catalog/card.webp', 'fixture');
        $item = new $model(['name' => 'Carta']);
        $item->setRelation('mediaAsset', new MediaAsset(['card_path' => 'catalog/card.webp']));
        $html = Blade::render('<x-catalog-image :item="$item" />', ['item' => $item]);
        $this->assertStringContainsString('src="'.e(Storage::disk('public')->url('catalog/card.webp')).'"', $html);
        $this->assertStringContainsString('alt="Carta"', $html);
    }

    public function test_absent_files_render_placeholder_instead_of_broken_image(): void
    {
        $item = $this->item('missing.jpg', new MediaAsset(['card_path' => 'missing.webp']));
        $html = Blade::render('<x-catalog-image :item="$item" />', ['item' => $item]);
        $this->assertStringContainsString('Sin imagen', $html);
        $this->assertStringNotContainsString('<img', $html);
    }

    public function test_private_original_is_never_used_as_public_fallback(): void
    {
        Storage::disk('local')->put('originals/private.jpg', 'private fixture');
        $media = new MediaAsset(['original_path' => 'originals/private.jpg']);
        $this->assertNull($this->resolve($this->item(null, $media)));
        // A legacy reference does not cause a lookup on the private disk either.
        $this->assertNull($this->resolve($this->item('originals/private.jpg')));
        Storage::disk('local')->assertExists('originals/private.jpg');
    }

    public static function unsafePaths(): array
    {
        return [
            ['https://example.com/photo.jpg'], ['//example.com/photo.jpg'],
            ['../private.jpg'], ['storage/../private.jpg'], ['/etc/passwd'],
            ['products/%2e%2e/private.jpg'], ['products/./photo.jpg'],
            ['data:image/png;base64,AAA'], ["products/\0photo.jpg"],
            ['C:\\private\\photo.jpg'], ['products/photo.jpg?redirect=1'],
        ];
    }

    #[DataProvider('unsafePaths')]
    public function test_invalid_paths_are_rejected_before_storage_lookup(string $path): void
    {
        $disk = \Mockery::mock(\Illuminate\Contracts\Filesystem\Filesystem::class);
        $disk->shouldNotReceive('exists');
        Storage::shouldReceive('disk')->once()->with('public')->andReturn($disk);
        $this->assertNull($this->resolve($this->item($path)));
    }

    public function test_alt_remains_escaped_and_loading_attribute_is_preserved(): void
    {
        Storage::disk('public')->put('legacy.jpg', 'fixture');
        $item = $this->item('legacy.jpg');
        $item->name = '"><script>alert(1)</script>';
        $html = Blade::render('<x-catalog-image :item="$item" loading="eager" />', ['item' => $item]);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString(e($item->name), $html);
        $this->assertStringContainsString('loading="eager"', $html);
    }
}
