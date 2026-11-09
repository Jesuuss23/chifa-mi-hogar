<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
    public function deliver(Request $request, Order $order): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['confirm_delivery' => ['required', 'accepted']]);

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $order) {
            $locked = Order::query()->whereKey($order->id)->lockForUpdate()->firstOrFail();
            abort_unless($request->user()?->isDelivery()
                && (int) $locked->delivery_user_id === (int) $request->user()->id, 403);
            abort_unless($locked->delivery_type === 'delivery', 422);

            // A repeated confirmation must not duplicate history or collection.
            if ($locked->status === 'delivered') {
                return;
            }
            if ($locked->status !== 'on_the_way') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'delivery' => 'Solo puedes entregar un pedido que está en camino.',
                ]);
            }

            if ($locked->payment_method === 'cash' && $locked->payment_status === 'pending') {
                $request->validate(['confirm_collection' => ['required', 'accepted']]);
                $locked->payment_status = 'verified';
                $locked->payment_verified_at = now();
                $locked->payment_verified_by = $request->user()->id;
            }
            if ($locked->payment_status !== 'verified') {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'payment' => 'El administrador debe verificar el pago antes de finalizar la entrega.',
                ]);
            }

            $locked->status = 'delivered';
            $locked->delivered_at = now();
            $locked->driver_tracking_stopped_at = now();
            $locked->driver_latitude = null;
            $locked->driver_longitude = null;
            $locked->driver_location_updated_at = null;
            $locked->save();
            $locked->statusHistories()->create([
                'from_status' => 'on_the_way',
                'to_status' => 'delivered',
                'changed_by' => $request->user()->id,
                'comment' => 'Entrega confirmada por el repartidor.',
            ]);
        }, 3);

        return redirect()->route('delivery.orders.show', $order)
            ->with('success', 'Pedido entregado correctamente.');
    }

    public function index(Request $request): View
    {
        $orders = Order::query()
            ->where('delivery_user_id', $request->user()->id)
            ->whereIn('status', [
                'confirmed',
                'preparing',
                'on_the_way',
            ])
            ->latest()
            ->paginate(15);

        return view('delivery.orders.index', [
            'orders' => $orders,
        ]);
    }

    public function show(Request $request, Order $order): View
    {
        abort_unless(
            (int) $order->delivery_user_id === (int) $request->user()->id,
            403
        );

        abort_unless(
            $order->delivery_type === 'delivery',
            404
        );

        return view('delivery.orders.show', [
            'order' => $order,
        ]);
    }
}
