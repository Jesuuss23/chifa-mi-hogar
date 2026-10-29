<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StorefrontComboCarouselTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
    }

    public function test_home_shows_public_combos_before_the_product_catalog_in_admin_order(): void
    {
        $last = $this->combo(['name' => 'Combo Zeta', 'sort_order' => 2]);
        $first = $this->combo(['name' => 'Combo Alfa', 'sort_order' => 1]);
        $response = $this->get(route('storefront.index'))->assertOk()
            ->assertViewHas('combos', fn ($combos) => $combos->modelKeys() === [$first->id, $last->id])
            ->assertSeeInOrder(['id="combos"', 'Combo Alfa', 'Combo Zeta', 'id="carta"'], false)
            ->assertSee('Personalizar combo')
            ->assertSee('S/ 35.00')
            ->assertSee('href="'.route('combos.show', $first).'"', false)
            ->assertSee('href="#combos"', false);
        $response->assertDontSee('href="'.route('combos.index').'"', false);
    }

    public function test_hidden_unavailable_and_deleted_combos_do_not_appear(): void
    {
        $visible = $this->combo(['name' => 'Disponible']);
        $this->combo(['name' => 'Oculto', 'show_in_menu' => false]);
        $this->combo(['name' => 'Agotado', 'is_available' => false]);
        $this->combo(['name' => 'Eliminado'])->delete();

        $this->get(route('storefront.index'))->assertOk()
            ->assertViewHas('combos', fn ($combos) => $combos->modelKeys() === [$visible->id])
            ->assertSee('Disponible')
            ->assertDontSee('Oculto')->assertDontSee('Agotado')->assertDontSee('Eliminado');
    }

    public function test_empty_catalog_has_no_empty_carousel_controls(): void
    {
        $this->get(route('storefront.index'))->assertOk()
            ->assertSee('id="combos"', false)
            ->assertDontSee('data-combo-carousel', false)
            ->assertDontSee('data-combo-controls', false)
            ->assertSee('No hay platos disponibles');
    }

    public function test_combo_items_and_legacy_image_are_shown(): void
    {
        $category = Category::create(['name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Chaufa especial',
            'slug' => 'chaufa-especial', 'price' => 20, 'is_available' => true,
        ]);
        $combo = $this->combo(['image_path' => 'combos/legacy.jpg']);
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 2]);

        $this->get(route('storefront.index'))->assertOk()
            ->assertSee('2 × Chaufa especial')
            ->assertSee(asset('storage/combos/legacy.jpg'), false);
    }

    public function test_category_filter_and_product_pagination_remain_independent(): void
    {
        $category = Category::create(['name' => 'Arroces', 'slug' => 'arroces', 'is_active' => true]);
        for ($i = 1; $i <= 13; $i++) {
            Product::create([
                'category_id' => $category->id, 'name' => 'Plato '.$i,
                'slug' => 'plato-'.$i, 'price' => 20, 'is_available' => true,
            ]);
        }
        $combo = $this->combo();
        $this->get(route('storefront.index', ['categoria' => 'arroces', 'page' => 2]))
            ->assertOk()
            ->assertViewHas('selectedCategory', fn ($selected) => $selected->is($category))
            ->assertViewHas('products', fn ($products) =>
                $products->currentPage() === 2 && $products->total() === 13 && $products->count() === 1)
            ->assertViewHas('combos', fn ($combos) => $combos->modelKeys() === [$combo->id]);
    }

    public function test_detail_keeps_cart_action_and_returns_to_home_combos(): void
    {
        $combo = $this->combo();
        $this->get(route('combos.show', $combo))->assertOk()
            ->assertSee('href="'.route('storefront.index').'#combos"', false)
            ->assertSee('action="'.route('cart.combos.store').'"', false);
        // Existing links and SEO for the previous listing stay valid.
        $this->get(route('combos.index'))->assertOk();
    }

    public function test_card_content_is_escaped(): void
    {
        $name = '<script>alert("combo")</script>';
        $this->combo(['name' => $name]);
        $this->get(route('storefront.index'))->assertOk()
            ->assertSee(e($name), false)->assertDontSee($name, false);
    }

    private function combo(array $overrides = []): Combo
    {
        return Combo::create(array_replace([
            'name' => 'Combo de la casa', 'slug' => 'combo-'.Str::uuid(),
            'price' => '35.00', 'is_available' => true, 'show_in_menu' => true, 'sort_order' => 0,
        ], $overrides));
    }
}
