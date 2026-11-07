<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Product;
use App\Models\User;
use App\Services\ProductOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductOrderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        Http::preventStrayRequests();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    private function category(): Category
    {
        $name = 'Categoria '.Str::ulid();
        return Category::create(['name' => $name, 'slug' => Str::slug($name),
            'sort_order' => 0, 'is_active' => true]);
    }

    private function data(int $order): array
    {
        return ['category_id' => $this->category()->id, 'name' => 'Plato '.Str::ulid(),
            'price' => '20.00', 'sort_order' => $order, 'image_path' => 'legacy.jpg',
            'is_available' => true, 'is_featured' => false];
    }

    private function product(int $order, array $overrides = []): Product
    {
        $data = array_replace($this->data($order), $overrides);
        return Product::create($data + ['slug' => Str::slug($data['name'])]);
    }

    private function extra(): Extra
    {
        $name = 'Extra '.Str::ulid();
        $category = ExtraCategory::create(['name' => $name, 'slug' => Str::slug($name),
            'sort_order' => 0, 'is_active' => true]);
        return Extra::create(['extra_category_id' => $category->id, 'name' => $name,
            'price' => '2.00', 'sort_order' => 0, 'is_available' => true]);
    }

    public function test_suggestion_includes_unavailable_products_and_all_categories(): void
    {
        $service = app(ProductOrderService::class);
        $this->assertSame(0, $service->suggestedOrder());
        $this->product(2);
        $this->product(8, ['is_available' => false]);
        $this->assertSame(9, $service->suggestedOrder());
    }

    public function test_suggestion_uses_gap_at_integer_limit(): void
    {
        $this->product(0);
        $this->product(2147483647);
        $this->assertSame(1, app(ProductOrderService::class)->suggestedOrder());
    }

    public function test_new_product_cannot_take_unavailable_position_in_another_category(): void
    {
        $occupied = $this->product(4, ['is_available' => false, 'is_featured' => true]);
        $data = $this->data(4);
        $this->assertNotSame($occupied->category_id, $data['category_id']);
        try {
            app(ProductOrderService::class)->save($data, []);
            $this->fail('Expected duplicate order rejection.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('sort_order', $exception->errors());
        }
        $this->assertDatabaseCount('products', 1);
    }

    public function test_free_order_creates_slug_and_synchronizes_extras(): void
    {
        $extra = $this->extra();
        $data = array_replace($this->data(3), ['name' => 'Arroz especial']);
        $product = app(ProductOrderService::class)->save($data, [$extra->id]);
        $this->assertSame('arroz-especial', $product->slug);
        $this->assertSame(3, $product->sort_order);
        $this->assertSame([$extra->id], $product->extras()->pluck('extras.id')->all());
    }

    public function test_legacy_ties_can_edit_other_fields_and_move_to_free_position(): void
    {
        $first = $this->product(2);
        $second = $this->product(2);
        $slug = $first->slug;
        $extra = $this->extra();
        $service = app(ProductOrderService::class);
        $service->save(['name' => 'Nuevo nombre', 'sort_order' => 2], [$extra->id], $first);
        $this->assertSame('Nuevo nombre', $first->fresh()->name);
        $this->assertSame($slug, $first->fresh()->slug);
        $service->save(['sort_order' => 7], [], $first);
        $this->assertSame(7, $first->fresh()->sort_order);
        $this->assertSame(2, $second->fresh()->sort_order);
        $this->assertSame(0, $first->extras()->count());
    }

    public function test_deleted_product_does_not_reserve_position(): void
    {
        $this->product(5)->delete();
        $this->assertSame(0, app(ProductOrderService::class)->suggestedOrder());
        $created = app(ProductOrderService::class)->save($this->data(5), []);
        $this->assertSame(5, $created->sort_order);
    }

    public function test_http_conflict_preserves_product_and_linked_extras(): void
    {
        $occupied = $this->product(3);
        $product = $this->product(8);
        $original = $this->extra();
        $replacement = $this->extra();
        $product->extras()->sync([$original->id]);
        $before = $product->fresh()->getAttributes();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->from(route('admin.products.edit', $product))
            ->put(route('admin.products.update', $product), [
                'category_id' => $occupied->category_id, 'name' => 'No guardar',
                'price' => '99.00', 'sort_order' => 3, 'is_available' => 0,
                'is_featured' => 1, 'extras' => [$replacement->id],
            ])->assertRedirect(route('admin.products.edit', $product))
            ->assertSessionHasErrors('sort_order');
        $this->assertSame($before, $product->fresh()->getAttributes());
        $this->assertSame([$original->id], $product->extras()->pluck('extras.id')->all());
        $this->assertSame(3, $occupied->fresh()->sort_order);
    }

    public function test_http_free_position_updates_product_and_extras(): void
    {
        $product = $this->product(8);
        $extra = $this->extra();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->put(route('admin.products.update', $product), [
                'category_id' => $product->category_id, 'name' => 'Nuevo nombre',
                'price' => '21.00', 'sort_order' => 9, 'is_available' => 1,
                'is_featured' => 1, 'extras' => [$extra->id],
            ])->assertRedirect(route('admin.products.index'))->assertSessionHasNoErrors();
        $this->assertSame(9, $product->fresh()->sort_order);
        $this->assertSame('21.00', $product->fresh()->price);
        $this->assertTrue($product->fresh()->is_featured);
        $this->assertSame([$extra->id], $product->extras()->pluck('extras.id')->all());
    }

    public function test_create_form_suggests_free_order(): void
    {
        $this->product(10);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.products.create'))->assertOk()
            ->assertViewHas('product', fn ($product) => $product->sort_order === 11)
            ->assertSee('Los destacados siguen apareciendo primero.');
    }

    public function test_catalog_keeps_featured_priority_before_numeric_order(): void
    {
        $ordinary = $this->product(0);
        $featured = $this->product(9, ['is_featured' => true]);
        $this->product(1, ['is_available' => false]);
        $this->get(route('storefront.index'))->assertOk()
            ->assertViewHas('products', fn ($products) =>
                $products->pluck('id')->all() === [$featured->id, $ordinary->id]);
    }

    public function test_availability_reports_owner_across_categories_without_mutation(): void
    {
        $product = $this->product(4, ['name' => '<b>Chaufa</b>', 'is_available' => false]);
        $before = $product->fresh()->getAttributes();
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('admin.products.order-availability', ['sort_order' => 4]))
            ->assertOk()->assertJsonPath('available', false)
            ->assertJsonPath('occupied_by', '<b>Chaufa</b>')
            ->assertJsonPath('suggested_order', 5)
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertTrue($response->headers->hasCacheControlDirective('no-store'));
        $this->assertSame($before, $product->fresh()->getAttributes());
    }

    public function test_availability_accepts_current_order_including_legacy_ties(): void
    {
        $first = $this->product(2);
        $this->product(2);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('admin.products.order-availability', [
                'sort_order' => 2, 'product_id' => $first->id,
            ]))->assertOk()->assertJsonPath('available', true)
            ->assertJsonPath('current', true)->assertJsonPath('occupied_by', null);
    }

    public function test_availability_treats_deleted_position_as_free(): void
    {
        $this->product(0)->delete();
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->getJson(route('admin.products.order-availability', ['sort_order' => 0]))
            ->assertOk()->assertJsonPath('available', true)->assertJsonPath('current', false);
    }

    public function test_availability_validates_input_and_edit_target(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        foreach ([-1, '1.5', 2147483648, 'abc', ['1']] as $order) {
            $this->getJson(route('admin.products.order-availability', ['sort_order' => $order]))
                ->assertUnprocessable()->assertJsonValidationErrors('sort_order');
        }
        $this->getJson(route('admin.products.order-availability', [
            'sort_order' => 1, 'product_id' => 999999,
        ]))->assertNotFound();
    }

    public function test_availability_requires_administrator(): void
    {
        $url = route('admin.products.order-availability', ['sort_order' => 1]);
        $this->getJson($url)->assertUnauthorized();
        foreach (['customer', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]))
                ->getJson($url)->assertForbidden();
        }
    }

    public function test_server_conflict_names_product_even_without_browser_check(): void
    {
        $this->product(3, ['name' => 'Chaufa especial']);
        try {
            app(ProductOrderService::class)->save($this->data(3), []);
            $this->fail('Expected occupied order rejection.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString('Chaufa especial', $exception->errors()['sort_order'][0]);
        }
    }
}
