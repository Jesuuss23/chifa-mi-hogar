<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\Product;
use App\Models\User;
use App\Services\CatalogSlugService;
use App\Services\ImageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogSlugTest extends TestCase
{
    use RefreshDatabase;

    private MediaAsset $asset;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $this->asset = MediaAsset::create([
            'original_path' => 'originals/test.jpg',
            'thumbnail_path' => 'catalog/thumbnails/test.webp',
            'card_path' => 'catalog/cards/test.webp',
            'detail_path' => 'catalog/details/test.webp',
            'original_name' => 'test.jpg', 'mime_type' => 'image/jpeg',
            'original_size' => 100, 'width' => 10, 'height' => 10,
        ]);
        $this->mock(ImageService::class, function ($mock) {
            $mock->shouldReceive('upload')->andReturn($this->asset);
        });
    }

    private function category(array $overrides = []): Category
    {
        return Category::create(array_replace([
            'name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true,
            'media_asset_id' => $this->asset->id,
        ], $overrides));
    }

    private function categoryData(string $name): array
    {
        return ['name' => $name, 'sort_order' => 0, 'is_active' => true,
            'image' => UploadedFile::fake()->image('category.jpg', 10, 10)];
    }

    private function productData(Category $category, string $name): array
    {
        return [
            'category_id' => $category->id, 'name' => $name, 'price' => 20,
            'sort_order' => 0, 'is_available' => true,
            'image' => UploadedFile::fake()->image('plate.jpg', 10, 10),
        ];
    }

    public function test_categories_with_equivalent_slugs_can_be_created(): void
    {
        $this->post(route('admin.categories.store'), $this->categoryData('Café'))
            ->assertRedirect(route('admin.categories.index'))->assertSessionHasNoErrors();
        $this->post(route('admin.categories.store'), $this->categoryData('Cafe'))
            ->assertRedirect(route('admin.categories.index'))->assertSessionHasNoErrors();
        $this->assertSame(['cafe', 'cafe-2'], Category::orderBy('id')->pluck('slug')->all());
    }

    public function test_products_with_same_name_can_be_created_without_conflicts(): void
    {
        $category = $this->category();
        for ($i = 0; $i < 2; $i++) {
            $this->post(route('admin.products.store'), $this->productData($category, 'Chaufa especial'))
                ->assertRedirect(route('admin.products.index'))->assertSessionHasNoErrors();
        }
        $this->assertSame(['chaufa-especial', 'chaufa-especial-2'], Product::orderBy('id')->pluck('slug')->all());
    }

    public function test_deleted_category_slug_remains_reserved(): void
    {
        $old = $this->category(['name' => 'Sopas', 'slug' => 'sopas']);
        $old->delete();
        $this->post(route('admin.categories.store'), $this->categoryData('Sopas'))->assertSessionHasNoErrors();
        $this->assertSame('sopas-2', Category::firstOrFail()->slug);
        $this->assertSame('sopas', Category::withTrashed()->findOrFail($old->id)->slug);
    }

    public function test_deleted_product_slug_remains_reserved(): void
    {
        $category = $this->category();
        $product = Product::create(['category_id' => $category->id, 'name' => 'Chaufa', 'slug' => 'chaufa', 'price' => 20]);
        $product->delete();
        $this->post(route('admin.products.store'), $this->productData($category, 'Chaufa'))->assertSessionHasNoErrors();
        $this->assertSame('chaufa-2', Product::firstOrFail()->slug);
    }

    public function test_renaming_category_keeps_existing_public_filter(): void
    {
        $category = $this->category();
        $this->put(route('admin.categories.update', $category), [
            'name' => 'Nueva carta', 'slug' => 'forged-slug', 'sort_order' => 0, 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame('arroces', $category->fresh()->slug);
        $this->get('/?categoria=arroces')->assertOk()->assertSee('Nueva carta');
    }

    public function test_renaming_product_keeps_existing_url(): void
    {
        $category = $this->category();
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Original', 'slug' => 'original',
            'price' => 20, 'is_available' => true, 'media_asset_id' => $this->asset->id,
        ]);
        $this->put(route('admin.products.update', $product), [
            'category_id' => $category->id, 'name' => 'Nuevo nombre', 'slug' => 'forged-slug',
            'price' => 25, 'sort_order' => 0, 'is_available' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame('original', $product->fresh()->slug);
        $this->assertSame('25.00', $product->fresh()->price);
        $this->get('/platos/original')->assertOk()->assertSee('Nuevo nombre');
    }

    public function test_empty_normalization_gets_nonempty_unique_fallbacks(): void
    {
        $service = app(CatalogSlugService::class);
        $first = $service->create(Category::class, ['name' => '🍜']);
        $second = $service->create(Category::class, ['name' => '🥟']);
        $this->assertSame('categoria', $first->slug);
        $this->assertSame('categoria-2', $second->slug);
    }

    public function test_truncation_keeps_suffix_inside_column_limit(): void
    {
        $service = app(CatalogSlugService::class);
        $name = str_repeat('ß', 100);
        $first = $service->create(Category::class, ['name' => $name]);
        $second = $service->create(Category::class, ['name' => $name]);
        $this->assertLessThanOrEqual(120, strlen($first->slug));
        $this->assertLessThanOrEqual(120, strlen($second->slug));
        $this->assertNotSame($first->slug, $second->slug);
    }

    public function test_category_name_limit_matches_database(): void
    {
        $this->post(route('admin.categories.store'), $this->categoryData(str_repeat('a', 101)))
            ->assertSessionHasErrors('name');
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_existing_numbered_slug_is_not_reused(): void
    {
        $this->category(['name' => 'Primera', 'slug' => 'sopa']);
        $this->category(['name' => 'Segunda', 'slug' => 'sopa-2']);
        $new = app(CatalogSlugService::class)->create(Category::class, ['name' => 'Sopa']);
        $this->assertSame('sopa-3', $new->slug);
    }

    public function test_collision_between_lookup_and_insert_is_retried_after_rollback(): void
    {
        $inserted = false;
        DB::listen(function ($query) use (&$inserted) {
            if (! $inserted && str_contains(strtolower($query->sql), 'select')
                && in_array('race', $query->bindings, true)) {
                // Insertar después del resultado "libre" y antes del INSERT del servicio.
                $inserted = true;
                DB::table('categories')->insert([
                    'name' => 'Otra operación', 'slug' => 'race',
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        });
        $created = app(CatalogSlugService::class)->create(Category::class, ['name' => 'Race']);
        $this->assertTrue($inserted);
        $this->assertSame('race-2', $created->slug);
        $this->assertDatabaseCount('categories', 2);
    }
}
