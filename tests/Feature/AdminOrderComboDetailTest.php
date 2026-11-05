<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AdminOrderComboDetailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
    }

    private function order(): Order
    {
        return Order::create([
            'code' => 'CHF-'.Str::ulid(),
            'customer_name' => 'Cliente de prueba', 'customer_phone' => '999999999',
            'delivery_type' => 'delivery', 'subtotal' => 90, 'total' => 90,
            'payment_method' => 'cash', 'payment_status' => 'pending', 'status' => 'pending',
        ]);
    }

    private function item(Order $order, string $type = 'combo')
    {
        return $order->items()->create([
            'item_type' => $type, 'catalog_item_id' => 999999,
            'item_name' => 'Nombre guardado del pedido', 'quantity' => 2,
            'unit_price' => 40, 'extras_unit_total' => 5, 'line_total' => 90,
        ]);
    }

    public function test_admin_reads_saved_components_and_quantities_without_recalculating_order(): void
    {
        $order = $this->order();
        $item = $this->item($order);
        $component = $item->comboComponents()->create([
            'component_type' => 'product', 'catalog_item_id' => 999999,
            'component_name' => 'Chaufa histórico', 'included_quantity' => 1,
            'selected_quantity' => 3, 'additional_quantity' => 2,
            'unit_price' => 2.50, 'additional_total' => 5,
        ]);
        $before = [$order->fresh()->getAttributes(), $item->fresh()->getAttributes(), $component->fresh()->getAttributes()];
        $response = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('Componentes seleccionados por combo')
            ->assertSee('Chaufa histórico')
            ->assertSee('S/ 5.00')
            ->assertSee('S/ 90.00');
        $html = $response->getContent();
        $this->assertMatchesRegularExpression('/Total a preparar en esta línea<\/dt>\s*<dd[^>]*>6<\/dd>/u', $html);
        $this->assertSame($before, [
            $order->fresh()->getAttributes(), $item->fresh()->getAttributes(), $component->fresh()->getAttributes(),
        ]);
    }

    public function test_component_names_are_escaped(): void
    {
        $item = $this->item($order = $this->order());
        $item->comboComponents()->create([
            'component_type' => 'extra', 'catalog_item_id' => 999999,
            'component_name' => '<script>alert(1)</script>', 'included_quantity' => 1,
            'selected_quantity' => 1, 'additional_quantity' => 0, 'unit_price' => 2, 'additional_total' => 0,
        ]);
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)
            ->assertDontSee('<script>alert(1)</script>', false)
            ->assertDontSee('Cargo adicional por combo:');
    }

    public function test_historical_combo_without_components_is_explicit(): void
    {
        $this->item($order = $this->order());
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.orders.show', $order))->assertOk()
            ->assertSee('No hay componentes guardados para este combo en el pedido.');
    }

    public function test_regular_product_does_not_show_combo_details(): void
    {
        $this->item($order = $this->order(), 'product');
        $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('admin.orders.show', $order))->assertOk()
            ->assertDontSee('Componentes seleccionados por combo');
    }

    public static function restrictedRoles(): array
    {
        return [['customer'], ['delivery']];
    }

    #[DataProvider('restrictedRoles')]
    public function test_non_admin_cannot_read_order_details(string $role): void
    {
        $order = $this->order();
        $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route('admin.orders.show', $order))->assertForbidden();
    }
}
