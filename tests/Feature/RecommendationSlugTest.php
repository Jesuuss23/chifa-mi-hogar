<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\ProductRecommendation;
use App\Models\User;
use App\Services\CatalogSlugService;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RecommendationSlugTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;
    private MediaAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $category = Category::create(['name' => 'Carta', 'slug' => 'carta', 'is_active' => true]);
        $this->product = Product::create([
            'category_id' => $category->id, 'name' => 'Chaufa', 'slug' => 'chaufa',
            'price' => 20, 'is_available' => true,
        ]);
        $this->asset = MediaAsset::create([
            'original_path' => 'originals/test.jpg',
            'thumbnail_path' => 'catalog/thumbnails/test.webp',
            'card_path' => 'catalog/cards/test.webp',
            'detail_path' => 'catalog/details/test.webp',
            'original_name' => 'test.jpg', 'mime_type' => 'image/jpeg',
            'original_size' => 100, 'width' => 10, 'height' => 10,
        ]);
        $this->mock(ImageService::class)->shouldReceive('upload')->andReturn($this->asset);
    }

    public function test_equivalent_names_can_be_saved_for_the_same_product(): void
    {
        foreach (['Café', 'Cafe'] as $name) {
            $this->post(route('admin.products.recommendations.store', $this->product), [
                'name' => $name, 'sort_order' => 0, 'is_active' => true,
                'image' => UploadedFile::fake()->image('recommendation.jpg', 10, 10),
            ])->assertRedirect(route('admin.products.recommendations.index', $this->product))
                ->assertSessionHasNoErrors();
        }
        $this->assertSame(['cafe', 'cafe-2'], ProductRecommendation::orderBy('id')->pluck('slug')->all());
    }

    public function test_different_products_can_use_the_same_slug(): void
    {
        $other = $this->otherProduct();
        $first = $this->createRecommendation('Especial');
        $second = app(CatalogSlugService::class)->create(ProductRecommendation::class, [
            'product_id' => $other->id, 'name' => 'Especial',
        ]);
        $this->assertSame('especial', $first->slug);
        $this->assertSame('especial', $second->slug);
    }

    public function test_soft_deleted_slug_stays_reserved_within_its_product(): void
    {
        $first = $this->createRecommendation('Especial');
        $first->delete();
        $second = $this->createRecommendation('Especial');

        $this->assertSame('especial-2', $second->slug);
        $this->assertSame('especial', ProductRecommendation::withTrashed()->findOrFail($first->id)->slug);
    }

    public function test_rename_keeps_slug_and_ignores_client_supplied_identity(): void
    {
        $first = $this->createRecommendation('Original');
        $this->createRecommendation('Especial');
        $other = $this->otherProduct();

        $this->put(route('admin.products.recommendations.update', [$this->product, $first]), [
            'name' => 'Especial',
            'slug' => 'especial',
            'product_id' => $other->id,
            'sort_order' => 2,
            'is_active' => true,
        ])->assertRedirect(route('admin.products.recommendations.index', $this->product))
            ->assertSessionHasNoErrors();

        $first->refresh();
        $this->assertSame('Especial', $first->name);
        $this->assertSame('original', $first->slug);
        $this->assertEquals($this->product->id, $first->product_id);
    }

    public function test_unicode_names_get_nonempty_slugs_within_the_column_limit(): void
    {
        $this->assertSame('recomendacion', $this->createRecommendation('🍜')->slug);
        $this->assertSame('recomendacion-2', $this->createRecommendation('🥟')->slug);
        $name = str_repeat('ß', 150);
        $first = $this->createRecommendation($name);
        $second = $this->createRecommendation($name);
        $this->assertLessThanOrEqual(180, strlen($first->slug));
        $this->assertLessThanOrEqual(180, strlen($second->slug));
        $this->assertNotSame($first->slug, $second->slug);
    }

    public function test_collision_after_lookup_is_retried_inside_outer_transaction(): void
    {
        $inserted = false;
        DB::listen(function ($query) use (&$inserted) {
            if (! $inserted && str_contains(strtolower($query->sql), 'select')
                && str_contains($query->sql, 'product_recommendations')
                && in_array('race', $query->bindings, true)) {
                $inserted = true;
                DB::table('product_recommendations')->insert([
                    'product_id' => $this->product->id, 'name' => 'Otra operación', 'slug' => 'race',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });

        $created = DB::transaction(fn () => $this->createRecommendation('Race'));
        $this->assertTrue($inserted);
        $this->assertSame('race-2', $created->slug);
        $this->assertDatabaseCount('product_recommendations', 2);
    }

    public function test_recommendation_cannot_be_updated_through_another_product(): void
    {
        $recommendation = $this->createRecommendation('Original');
        $before = $recommendation->fresh()->getAttributes();
        $other = $this->otherProduct();

        $this->put(route('admin.products.recommendations.update', [$other, $recommendation]), [
            'name' => 'Modificada', 'sort_order' => 0, 'is_active' => true,
        ])->assertNotFound();
        $this->assertSame($before, $recommendation->fresh()->getAttributes());
    }

    private function createRecommendation(string $name): ProductRecommendation
    {
        return app(CatalogSlugService::class)->create(ProductRecommendation::class, [
            'product_id' => $this->product->id,
            'name' => $name,
            'media_asset_id' => $this->asset->id,
        ]);
    }

    private function otherProduct(): Product
    {
        return Product::create([
            'category_id' => $this->product->category_id,
            'name' => 'Tallarín', 'slug' => 'tallarin',
            'price' => 22, 'is_available' => true,
        ]);
    }
}
