<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderController extends Controller
{
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
