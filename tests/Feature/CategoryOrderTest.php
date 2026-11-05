<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MediaAsset;
use App\Models\User;
use App\Services\CategoryOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class CategoryOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    private function category(int $order, bool $active = true): Category
    {
        $name = 'Categoria '.Str::ulid();
        return Category::create(['name' => $name, 'slug' => Str::slug($name),
            'sort_order' => $order, 'is_active' => $active]);
    }

    public function test_suggestion_follows_highest_existing_order_including_inactive(): void
    {
        $service = app(CategoryOrderService::class);
        $this->assertSame(0, $service->suggestedOrder());
        $this->category(2);
        $this->category(8, false);
        $this->assertSame(9, $service->suggestedOrder());
    }

    public function test_suggestion_uses_free_gap_when_integer_limit_is_reached(): void
    {
        $this->category(0);
        $this->category(2147483647);
        $this->assertSame(1, app(CategoryOrderService::class)->suggestedOrder());
    }

    public function test_new_category_cannot_take_inactive_category_position(): void
    {
        $this->category(4, false);
        try {
            app(CategoryOrderService::class)->save(['name' => 'Nueva', 'sort_order' => 4]);
            $this->fail('Expected duplicate order rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sort_order', $exception->errors());
        }
        $this->assertDatabaseCount('categories', 1);
    }

    public function test_free_order_creates_category_with_existing_slug_generation(): void
    {
        $category = app(CategoryOrderService::class)->save(['name' => 'Arroces', 'sort_order' => 3, 'is_active' => true]);
        $this->assertSame('arroces', $category->slug);
        $this->assertSame(3, $category->sort_order);
    }

    public function test_legacy_ties_can_edit_other_fields_and_be_resolved_explicitly(): void
    {
        $first = $this->category(2);
        $second = $this->category(2);
        $slug = $first->slug;
        app(CategoryOrderService::class)->save(['name' => 'Nuevo nombre', 'sort_order' => 2], $first);
        $this->assertSame($slug, $first->fresh()->slug);
        app(CategoryOrderService::class)->save(['sort_order' => 7], $first);
        $this->assertSame(7, $first->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
    }

    public function test_deleted_category_does_not_reserve_position(): void
    {
        $this->category(5)->delete();
        $created = app(CategoryOrderService::class)->save(['name' => 'Reemplazo', 'sort_order' => 5]);
        $this->assertSame(5, $created->sort_order);
    }

    public function test_http_conflict_returns_field_error_without_mutation(): void
    {
        $occupied = $this->category(3);
        $category = $this->category(8);
        $asset = MediaAsset::create([
            'original_path' => 'original.jpg', 'thumbnail_path' => 'thumb.webp',
            'card_path' => 'card.webp', 'detail_path' => 'detail.webp',
            'original_name' => 'image.jpg', 'mime_type' => 'image/jpeg',
            'original_size' => 100, 'width' => 10, 'height' => 10,
        ]);
        $category->update(['media_asset_id' => $asset->id]);
        $before = $category->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('admin.categories.edit', $category))
            ->put(route('admin.categories.update', $category), [
                'name' => 'Nombre que no debe guardarse', 'sort_order' => 3, 'is_active' => 1,
            ])->assertRedirect(route('admin.categories.edit', $category))->assertSessionHasErrors('sort_order');
        $this->assertSame($before, $category->fresh()->getAttributes());
        $this->assertSame(3, $occupied->fresh()->sort_order);
    }

    public function test_create_form_suggests_available_order(): void
    {
        $this->category(10);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.categories.create'))->assertOk()
            ->assertViewHas('category', fn ($category) => $category->sort_order === 11)
            ->assertSee('las categorías inactivas también reservan su orden.');
    }
}
