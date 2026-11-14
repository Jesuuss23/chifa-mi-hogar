<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Extra;
use App\Models\Product;
use App\Models\QuickSale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class QuickSaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array']);
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-14 12:00:00', 'UTC'));
    }

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);
        return $admin;
    }

    private function product(): Product
    {
        $category = Category::create(['name' => 'Chaufa', 'slug' => 'c-'.Str::uuid(), 'is_active' => true]);
        return Product::create(['name' => 'Chaufa especial', 'slug' => 'p-'.Str::uuid(), 'category_id' => $category->id, 'price' => '12.50', 'is_available' => true]);
    }

    private function payload(Product $product, array $changes = []): array
    {
        return array_replace([
            'submission_key' => (string) Str::uuid(), 'sold_on' => '2026-11-14',
            'payment_method' => 'cash', 'customer_name' => null,
            'items' => [['type' => 'product', 'id' => $product->id, 'quantity' => 2]],
        ], $changes);
    }

    private function save(array $data)
    {
        return $this->post(route('admin.quick-sales.store'), $data);
    }

    public function test_admin_saves_multiple_items_and_extras_without_customer_or_reason(): void
    {
        $admin = $this->admin();
        $product = $this->product();
        $extra = Extra::create(['name' => 'Gaseosa', 'price' => '3.50', 'is_available' => true]);
        $data = $this->payload($product);
        $data['items'][] = ['type' => 'extra', 'id' => $extra->id, 'quantity' => 2, 'unit_price_cents' => 1];
        $data['created_by'] = 99999;
        $this->get(route('admin.quick-sales.create'))->assertOk()->assertSee('data-quick-sale', false)->assertDontSee('name="reason"', false);
        $this->save($data)->assertSessionHasNoErrors()->assertRedirect();
        $sale = QuickSale::firstOrFail();
        $this->assertSame(3200, $sale->total_cents);
        $this->assertNull($sale->customer_name);
        $this->assertSame($admin->id, $sale->created_by);
        $this->assertCount(2, $sale->items);
        $this->assertSame(350, $sale->items[1]['unit_price_cents']);
        $this->assertDatabaseCount('orders', 0);
        $this->get(route('admin.quick-sales.show', $sale))->assertOk()->assertSee('32.00')->assertSee('Sin nombre');
    }

    public function test_existing_combo_can_be_selected_with_products_and_extras_and_keeps_historical_price(): void
    {
        $this->admin();
        $product = $this->product();
        $combo = Combo::create(['name' => 'Dúo chifero', 'slug' => 'duo', 'price' => '22.99',
            'is_available' => false, 'availability_mode' => 'dates', 'available_dates' => ['2020-01-01']]);
        $extra = Extra::create(['name' => 'Gaseosa', 'price' => '3.50']);
        $this->get(route('admin.quick-sales.create'))->assertOk()->assertSee('optgroup label="Combos"', false)->assertSee('Dúo chifero');
        $data = $this->payload($product);
        $data['items'][] = ['type' => 'combo', 'id' => $combo->id, 'quantity' => 2, 'price' => '0.01'];
        $data['items'][] = ['type' => 'extra', 'id' => $extra->id, 'quantity' => 1];
        $this->save($data)->assertSessionHasNoErrors();
        $sale = QuickSale::firstOrFail();
        $this->assertSame(7448, $sale->total_cents);
        $this->assertSame('combo', $sale->items[1]['type']);
        $this->assertSame(2299, $sale->items[1]['unit_price_cents']);
        $combo->update(['name' => 'Nuevo nombre', 'price' => 50]);
        $combo->delete();
        $this->save($data)->assertSessionHasNoErrors();
        $this->assertDatabaseCount('quick_sales', 1);
        $this->get(route('admin.quick-sales.show', $sale))->assertOk()->assertSee('Dúo chifero')->assertSee('74.48');
    }

    public function test_deleted_combo_is_rejected_for_new_sale(): void
    {
        $this->admin();
        $product = $this->product();
        $combo = Combo::create(['name' => 'Combo eliminado', 'slug' => 'deleted', 'price' => '22.99']);
        $combo->delete();
        $this->save($this->payload($product, ['items' => [['type' => 'combo', 'id' => $combo->id, 'quantity' => 1]]]))
            ->assertSessionHasErrors('items.0.id');
        $this->assertDatabaseCount('quick_sales', 0);
    }

    public function test_admin_can_override_total_without_a_reason_and_prices_are_preserved(): void
    {
        $this->admin();
        $product = $this->product();
        $this->save($this->payload($product, ['total' => '22.99', 'payment_method' => 'yape', 'customer_name' => 'Rossi']))->assertSessionHasNoErrors();
        $sale = QuickSale::firstOrFail();
        $this->assertSame(2299, $sale->total_cents);
        $this->assertSame(2500, $sale->catalog_total_cents);
        $product->update(['name' => 'Otro nombre', 'price' => 100]);
        $product->delete();
        $this->get(route('admin.quick-sales.show', $sale))->assertOk()->assertSee('Chaufa especial')->assertSee('22.99')->assertSee('Yape');
    }

    public function test_double_submit_returns_same_sale_even_if_catalog_changed(): void
    {
        $this->admin();
        $product = $this->product();
        $data = $this->payload($product);
        $first = $this->save($data)->assertSessionHasNoErrors();
        $product->delete();
        $second = $this->save($data)->assertSessionHasNoErrors();
        $this->assertSame($first->headers->get('Location'), $second->headers->get('Location'));
        $this->assertDatabaseCount('quick_sales', 1);
    }

    public function test_same_key_cannot_change_saved_data_or_be_used_by_another_admin(): void
    {
        $this->admin();
        $data = $this->payload($this->product());
        $this->save($data)->assertSessionHasNoErrors();
        $this->save(array_replace($data, ['total' => '1.00']))->assertStatus(409);
        $this->admin();
        $this->save($data)->assertStatus(409);
        $this->assertDatabaseCount('quick_sales', 1);
        $this->assertSame(2500, QuickSale::first()->total_cents);
    }

    public function test_invalid_money_quantity_payment_and_future_date_are_rejected(): void
    {
        $this->admin();
        $data = $this->payload($this->product());
        foreach (['-1', '0.001', '100000', '1e5', 'NaN', '1,50'] as $total) {
            $this->save(array_replace($data, ['total' => $total]))->assertSessionHasErrors('total');
        }
        $this->save(array_replace($data, ['items' => []]))->assertSessionHasErrors('items');
        $this->save(array_replace($data, ['items' => [['type' => 'product', 'id' => 1, 'quantity' => 0]]]))->assertSessionHasErrors('items.0.quantity');
        $this->save(array_replace($data, ['payment_method' => 'verified', 'sold_on' => '2026-11-15']))->assertSessionHasErrors(['payment_method', 'sold_on']);
        $this->save(array_replace($data, ['items' => [['type' => 'product', 'id' => 999999, 'quantity' => 1]]]))->assertSessionHasErrors('items.0.id');
        $this->assertDatabaseCount('quick_sales', 0);
    }

    public function test_guest_customer_and_delivery_cannot_access_module(): void
    {
        $this->get(route('admin.quick-sales.index'))->assertRedirect();
        foreach (['customer', 'delivery'] as $role) {
            $this->actingAs(User::factory()->create(['role' => $role]));
            $this->get(route('admin.quick-sales.index'))->assertForbidden();
            $this->get(route('admin.quick-sales.create'))->assertForbidden();
            $this->save([])->assertForbidden();
        }
    }

    public function test_csrf_is_required_outside_test_bypass(): void
    {
        $this->admin();
        $data = $this->payload($this->product());
        $this->app->detectEnvironment(fn () => 'production');
        try {
            $this->save($data)->assertStatus(419);
        } finally {
            $this->app->detectEnvironment(fn () => 'testing');
        }
        $this->assertDatabaseCount('quick_sales', 0);
    }

    public function test_date_filters_totals_and_grouping_respect_saved_final_amount(): void
    {
        $this->admin();
        $product = $this->product();
        foreach ([['2025-12-31', 'cash', '10.00'], ['2026-10-31', 'cash', '20.00'], ['2026-11-01', 'yape', '30.00'], ['2026-11-14', 'cash', '22.99']] as [$day, $method, $total]) {
            $this->save($this->payload($product, ['sold_on' => $day, 'payment_method' => $method, 'total' => $total]))->assertSessionHasNoErrors();
        }
        $this->get(route('admin.quick-sales.index'))->assertOk()->assertViewHas('totals', fn ($totals) => (int) $totals->sum('amount') === 2299);
        $this->get(route('admin.quick-sales.index', ['period' => 'month']))->assertOk()->assertViewHas('totals', fn ($totals) => (int) $totals->sum('amount') === 5299);
        $this->get(route('admin.quick-sales.index', ['period' => 'year']))->assertOk()
            ->assertViewHas('totals', fn ($totals) => (int) $totals->sum('amount') === 7299)
            ->assertViewHas('summary', fn ($rows) => $rows->total() === 2);
        $this->get(route('admin.quick-sales.index', ['period' => 'year', 'payment_method' => 'yape']))->assertOk()->assertViewHas('totals', fn ($totals) => (int) $totals->sum('amount') === 3000);
        $this->get(route('admin.quick-sales.index', ['period' => 'custom', 'from' => '2025-12-31', 'to' => '2026-11-14', 'group' => 'year']))->assertOk()
            ->assertViewHas('totals', fn ($totals) => (int) $totals->sum('amount') === 8299)
            ->assertViewHas('summary', fn ($rows) => $rows->total() === 2);
    }

    public function test_summary_includes_all_matching_rows_not_only_page(): void
    {
        $this->admin();
        $product = $this->product();
        for ($i = 0; $i < 21; $i++) {
            $this->save($this->payload($product, ['total' => '1.01', 'customer_name' => 'Ana']))->assertSessionHasNoErrors();
        }
        $this->get(route('admin.quick-sales.index', ['search' => 'Ana']))->assertOk()
            ->assertViewHas('sales', fn ($rows) => $rows->count() === 20 && $rows->total() === 21)
            ->assertViewHas('totals', fn ($totals) => (int) $totals->sum('amount') === 2121);
    }

    public function test_lima_date_is_used_near_utc_midnight(): void
    {
        $this->admin();
        $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-15 02:00:00', 'UTC'));
        $this->get(route('admin.quick-sales.create'))->assertOk()->assertSee('value="2026-11-14"', false);
        $this->save($this->payload($this->product(), ['sold_on' => '2026-11-15']))->assertSessionHasErrors('sold_on');
    }

    public function test_customer_markup_is_escaped_and_invalid_filters_are_rejected(): void
    {
        $this->admin();
        $this->save($this->payload($this->product(), ['customer_name' => '<script>alert(1)</script>']))->assertSessionHasNoErrors();
        $this->get(route('admin.quick-sales.show', QuickSale::first()))->assertOk()->assertDontSee('<script>alert(1)</script>', false)->assertSee('&lt;script&gt;', false);
        foreach ([['period' => 'invalid'], ['group' => 'year); DROP TABLE users'], ['search' => ['bad']], ['period' => 'custom', 'from' => '2026-11-14', 'to' => '2026-11-01']] as $filters) {
            $this->getJson(route('admin.quick-sales.index', $filters))->assertUnprocessable();
        }
    }

    public function test_storage_enforces_unique_submission_key(): void
    {
        $this->admin();
        $this->save($this->payload($this->product()))->assertSessionHasNoErrors();
        $row = (array) DB::table('quick_sales')->first();
        unset($row['id']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        DB::table('quick_sales')->insert($row);
    }
}
