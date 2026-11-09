<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Product;
use App\Models\User;
use App\Services\ComboService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ComboAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        $this->withoutVite();
        // Tuesday UTC is still Monday in Peru.
        $this->travelTo(CarbonImmutable::parse('2026-11-10 04:59:00', 'UTC'));
    }

    private function combo(array $attributes = []): Combo
    {
        $category = Category::create(['name' => 'Carta', 'slug' => uniqid('c-'), 'is_active' => true]);
        $product = Product::create([
            'category_id' => $category->id, 'name' => 'Chaufa', 'slug' => uniqid('p-'),
            'price' => '20.00', 'is_available' => true,
        ]);
        $combo = Combo::create(array_replace([
            'name' => 'Combo prueba', 'slug' => uniqid('combo-'), 'price' => '35.00',
            'is_available' => true, 'show_in_menu' => true,
        ], $attributes));
        $combo->items()->create(['product_id' => $product->id, 'quantity' => 2]);
        return $combo->refresh();
    }

    private function line(Combo $combo, array $overrides = []): array
    {
        return app(ComboService::class)->buildLine(array_replace([
            'id' => $combo->id, 'quantity' => 2,
        ], $overrides));
    }

    public function test_existing_combos_default_to_every_day_and_quantity_multiplies_fixed_price(): void
    {
        $combo = $this->combo();
        $this->assertSame('always', $combo->availability_mode);
        $line = $this->line($combo);
        $this->assertSame(7000, $line['line_total_cents']);
        $this->assertSame(0, $line['extras_cents']);
        $this->assertSame(2, $line['components'][0]['selected_quantity']);
    }

    public function test_weekday_schedule_uses_peru_midnight_and_blocks_stale_checkout(): void
    {
        $combo = $this->combo(['availability_mode' => 'weekdays', 'available_weekdays' => [1]]);
        $this->assertSame(7000, $this->line($combo)['line_total_cents']);
        $this->travelTo(CarbonImmutable::parse('2026-11-10 05:00:00', 'UTC'));
        $this->assertFalse(Combo::availableNow()->whereKey($combo->id)->exists());
        $this->expectException(ValidationException::class);
        $this->line($combo);
    }

    public function test_exact_dates_hide_unavailable_combos_and_direct_links(): void
    {
        $available = $this->combo(['availability_mode' => 'dates', 'available_dates' => ['2026-11-09']]);
        $unavailable = $this->combo(['availability_mode' => 'dates', 'available_dates' => ['2026-12-09']]);
        $this->get(route('storefront.index'))->assertOk()
            ->assertViewHas('combos', fn ($items) => $items->modelKeys() === [$available->id]);
        $this->get(route('combos.index'))->assertOk()
            ->assertViewHas('combos', fn ($items) => $items->modelKeys() === [$available->id]);
        $this->get(route('combos.show', $unavailable))->assertNotFound();
        $this->post(route('cart.combos.store'), ['combo_id' => $unavailable->id, 'quantity' => 1])->assertNotFound();
        $this->travelTo(CarbonImmutable::parse('2026-11-10 05:00:00', 'UTC'));
        $this->expectException(ValidationException::class);
        $this->line($available);
    }

    public function test_disabled_combo_is_never_available_even_on_its_selected_date(): void
    {
        $combo = $this->combo(['is_available' => false, 'availability_mode' => 'dates', 'available_dates' => ['2026-11-09']]);
        $this->assertFalse(Combo::availableNow()->whereKey($combo->id)->exists());
        $this->expectException(ValidationException::class);
        $this->line($combo);
    }

    public function test_component_changes_are_rejected_by_authoritative_pricing(): void
    {
        $combo = $this->combo();
        $component = ['type' => 'product', 'id' => $combo->items->first()->product_id, 'quantity' => 2];
        $this->assertSame(7000, $this->line($combo, ['components' => [$component]])['line_total_cents']);
        $component['quantity'] = 3;
        $this->expectException(ValidationException::class);
        $this->line($combo, ['components' => [$component]]);
    }

    public function test_cart_rejects_customizations_without_changing_session(): void
    {
        $combo = $this->combo();
        $cart = ['fixed' => ['type' => 'combo', 'combo_id' => $combo->id, 'quantity' => 1, 'components' => []]];
        $this->withSession(['cart' => $cart])->patch(route('cart.update', 'fixed'), [
            'edit_mode' => 'configuration', 'edit_scope' => 'all',
        ])->assertSessionHasErrors('cart')->assertSessionHas('cart', $cart);
        $this->post(route('cart.combos.store'), [
            'combo_id' => $combo->id, 'quantity' => 1,
            'components' => [['type' => 'product', 'id' => $combo->items->first()->product_id, 'quantity' => 3]],
        ])->assertSessionHasErrors('components')->assertSessionHas('cart', $cart);
    }

    public function test_quantity_can_change_and_invalid_legacy_combo_has_visible_notice(): void
    {
        $combo = $this->combo();
        $cart = ['fixed' => ['type' => 'combo', 'combo_id' => $combo->id, 'quantity' => 1, 'components' => []]];
        $this->withSession(['cart' => $cart])->patch(route('cart.update', 'fixed'), ['quantity' => 3])
            ->assertRedirect(route('cart.index'))->assertSessionHas('cart.fixed.quantity', 3);
        $this->get(route('cart.index'))->assertOk()->assertViewHas('subtotal', 105);
        $cart['fixed']['components'] = [['type' => 'product', 'id' => $combo->items->first()->product_id, 'quantity' => 3]];
        $this->withSession(['cart' => $cart])->get(route('cart.index'))->assertOk()
            ->assertSee('Retiramos un combo')->assertSessionHas('cart', []);
    }

    public function test_reference_price_is_real_and_only_crossed_out_when_greater(): void
    {
        $combo = $this->combo();
        foreach (['storefront.index', 'combos.index', 'combos.show'] as $route) {
            $response = $this->get(route($route, $route === 'combos.show' ? $combo : []))->assertOk()
                ->assertSee('<del>S/ 40.00</del>', false);
            if ($route === 'combos.show') {
                $response->assertDontSee('name="components[', false)->assertSee('Cantidad de combos');
            }
        }
        $combo->update(['price' => '40.00']);
        $this->get(route('combos.show', $combo))->assertOk()->assertDontSee('<del>', false);
    }

    public function test_admin_can_save_weekdays_and_replace_them_with_exact_dates(): void
    {
        $combo = $this->combo();
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $data = [
            'name' => $combo->name, 'price' => '35.00', 'show_in_menu' => 1, 'is_available' => 1,
            'items' => [['product_id' => $combo->items->first()->product_id, 'quantity' => 2]],
            'availability_mode' => 'weekdays', 'available_weekdays' => ['1', '5'],
        ];
        $this->put(route('admin.combos.update', $combo), $data)->assertSessionHasNoErrors();
        $this->assertSame([1, 5], $combo->fresh()->available_weekdays);
        $data['availability_mode'] = 'dates';
        $data['available_dates'] = "2026-11-09\n2026-12-25";
        $this->put(route('admin.combos.update', $combo), $data)->assertSessionHasNoErrors();
        $this->assertNull($combo->fresh()->available_weekdays);
        $this->assertSame(['2026-11-09', '2026-12-25'], $combo->fresh()->available_dates);
        $data['available_dates'] = '2026-02-30';
        $this->put(route('admin.combos.update', $combo), $data)->assertSessionHasErrors('available_dates.0');
        $this->assertSame(['2026-11-09', '2026-12-25'], $combo->fresh()->available_dates);
        $data['availability_mode'] = 'weekdays';
        $data['available_weekdays'] = [];
        $this->put(route('admin.combos.update', $combo), $data)->assertSessionHasErrors('available_weekdays');
    }

    public function test_expired_cart_cannot_be_updated_or_priced_for_checkout(): void
    {
        $combo = $this->combo(['availability_mode' => 'dates', 'available_dates' => ['2026-11-08']]);
        $cart = ['fixed' => ['type' => 'combo', 'combo_id' => $combo->id, 'quantity' => 1, 'components' => []]];
        $this->withSession(['cart' => $cart])->patch(route('cart.update', 'fixed'), ['quantity' => 2])
            ->assertSessionHasErrors('items')->assertSessionHas('cart', $cart);
        $this->expectException(ValidationException::class);
        app(\App\Services\OrderService::class)->previewLine(['type' => 'combo', 'id' => $combo->id, 'quantity' => 1]);
    }

    public function test_extra_injection_and_reducing_components_are_rejected(): void
    {
        $combo = $this->combo();
        foreach ([
            ['extras' => [['id' => 123, 'quantity' => 1]]],
            ['components' => [['type' => 'product', 'id' => $combo->items->first()->product_id, 'quantity' => 1]]],
        ] as $override) {
            try {
                $this->line($combo, $override);
                $this->fail('A fixed combo must reject modifications.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('items', $exception->errors());
            }
        }
    }

    public function test_customer_cannot_change_combo_schedule(): void
    {
        $combo = $this->combo();
        $this->actingAs(User::factory()->create(['role' => 'customer']))
            ->put(route('admin.combos.update', $combo), ['availability_mode' => 'always'])->assertForbidden();
    }
}
