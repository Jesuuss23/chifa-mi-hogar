<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminOrderActionRedirectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('a', 32)), 'cache.default' => 'array']);
        $this->actingAs(User::factory()->create(['role' => 'admin']));
    }

    private function order(array $attributes = []): Order
    {
        return Order::create(array_replace([
            'code' => 'CHF-'.Str::ulid(), 'customer_name' => 'Cliente', 'customer_phone' => '999999999',
            'delivery_type' => 'delivery', 'subtotal' => 20, 'total' => 20,
            'payment_method' => 'cash', 'payment_status' => 'pending', 'status' => 'pending',
        ], $attributes));
    }

    private function legacyPoll(Order $order): void
    {
        $this->get(route('admin.orders.show', $order))->assertOk();
        // fetch before the fix sent Accept JSON, but no X-Requested-With. Admin pages suppress Referer.
        $this->get(route('admin.orders.updates', ['ids' => [$order->id]]), ['Accept' => 'application/json'])->assertOk();
    }

    public function test_status_success_returns_to_html_detail_after_background_poll(): void
    {
        $order = $this->order();
        $this->legacyPoll($order);
        $response = $this->patch(route('admin.orders.status', $order), ['status' => 'confirmed']);
        $response->assertRedirect(route('admin.orders.show', $order))->assertSessionHas('success');
        $this->get($response->headers->get('Location'))->assertOk()->assertSee('Actualizar estado')->assertSee('Estado actualizado correctamente.');
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(1, $order->statusHistories()->count());
    }

    public function test_business_failure_and_duplicate_submit_return_to_same_order(): void
    {
        $order = $this->order(['status' => 'confirmed', 'payment_method' => 'yape']);
        $this->legacyPoll($order);
        $this->patch(route('admin.orders.status', $order), ['status' => 'preparing', 'comment' => 'Revisar pago'])
            ->assertRedirect(route('admin.orders.show', $order))->assertSessionHasErrors('status');
        $this->assertSame('confirmed', $order->fresh()->status);
        $this->assertSame(0, $order->statusHistories()->count());
    }

    public function test_invalid_form_returns_to_detail_and_preserves_values(): void
    {
        $order = $this->order();
        $this->legacyPoll($order);
        $this->patch(route('admin.orders.status', $order), ['status' => 'cancelled', 'comment' => 'Cliente llamó'])
            ->assertRedirect(route('admin.orders.show', $order))->assertSessionHasErrors('cancellation_reason');
        $this->get(route('admin.orders.show', $order))->assertOk()->assertSee('Cliente llamó')
            ->assertSee('value="cancelled" selected', false);
        $this->assertSame('pending', $order->fresh()->status);
    }

    public function test_payment_cash_and_driver_actions_also_ignore_poll_return_url(): void
    {
        $digital = $this->order(['payment_method' => 'yape']);
        $cash = $this->order(['status' => 'confirmed']);
        $driver = User::factory()->create(['role' => 'delivery']);
        foreach ([[$digital, 'admin.orders.payment', ['decision' => 'verified']],
            [$cash, 'admin.orders.collect-cash', ['confirm_collection' => '1']],
            [$cash, 'admin.orders.assign-driver', ['delivery_user_id' => $driver->id]]] as [$order, $route, $data]) {
            $this->legacyPoll($order);
            $this->patch(route($route, $order), $data)->assertRedirect(route('admin.orders.show', $order))->assertSessionHasNoErrors();
        }
        $this->assertSame('verified', $digital->fresh()->payment_status);
        $this->assertSame('verified', $cash->fresh()->payment_status);
        $this->assertSame($driver->id, $cash->fresh()->delivery_user_id);
    }

    public function test_invalid_payment_cash_and_driver_forms_return_to_detail(): void
    {
        $order = $this->order();
        foreach (['admin.orders.payment' => 'decision', 'admin.orders.collect-cash' => 'confirm_collection', 'admin.orders.assign-driver' => 'delivery_user_id'] as $route => $field) {
            $this->legacyPoll($order);
            $this->patch(route($route, $order), [])->assertRedirect(route('admin.orders.show', $order))->assertSessionHasErrors($field);
        }
    }

    public function test_ajax_poll_does_not_replace_previous_page(): void
    {
        $order = $this->order();
        $detail = route('admin.orders.show', $order);
        $this->get($detail)->assertOk();
        $this->get(route('admin.orders.updates', ['ids' => [$order->id]]), [
            'Accept' => 'application/json', 'X-Requested-With' => 'XMLHttpRequest',
        ])->assertOk()->assertSessionHas('_previous.url', $detail);
    }

    public function test_repeated_status_request_does_not_duplicate_history(): void
    {
        $order = $this->order();
        $this->patch(route('admin.orders.status', $order), ['status' => 'confirmed']);
        $this->legacyPoll($order);
        $this->patch(route('admin.orders.status', $order), ['status' => 'confirmed'])
            ->assertRedirect(route('admin.orders.show', $order))->assertSessionHasErrors('status');
        $this->assertSame(1, $order->statusHistories()->count());
    }
}
