<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderRating;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrderRatingController extends Controller
{
    public function store(Request $request, Order $order): RedirectResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        DB::transaction(function () use ($request, $order, $data) {

            $order = Order::query()
                ->whereKey($order->id)
                ->where('user_id', $request->user()->id)
                ->firstOrFail();

            if ($order->status !== 'delivered') {
                throw ValidationException::withMessages([
                    'rating' => 'Solo puedes valorar pedidos entregados.',
                ]);
            }

            if ($order->rating()->exists()) {
                throw ValidationException::withMessages([
                    'rating' => 'Este pedido ya fue valorado.',
                ]);
            }

            OrderRating::create([
                'user_id' => $request->user()->id,
                'order_id' => $order->id,
                'rating' => $data['rating'],
                'comment' => $data['comment'] ?? null,
            ]);
        });

        return redirect()
            ->route('customer.account')
            ->with('success', '¡Gracias por valorar tu experiencia!');
    }
}