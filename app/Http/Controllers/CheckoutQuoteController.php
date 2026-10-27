<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

class CheckoutQuoteController extends Controller
{
    public function store(
        Request $request,
        OrderService $orderService
    ): JsonResponse {
        $data = $request->validate([
            'delivery_type' => [
                'required',
                Rule::in(['pickup', 'delivery']),
            ],

            'delivery_latitude' => [
                'required_if:delivery_type,delivery',
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'delivery_longitude' => [
                'required_if:delivery_type,delivery',
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'payment_method' => [
                'required',
                Rule::in(['yape', 'plin', 'cash']),
            ],

            'cash_payment_type' => [
                'required_if:payment_method,cash',
                'nullable',
                Rule::in(['exact', 'change']),
            ],

            'cash_received_amount' => [
                'required_if:cash_payment_type,change',
                'nullable',
                'numeric',
                'decimal:0,2',
                'min:0',
                'max:9999999999.99',
            ],

            'items' => [
                'required',
                'array',
                'min:1',
                'max:30',
            ],

            'items.*.type' => [
                'required',
                Rule::in(['product', 'recommendation', 'combo']),
            ],

            'items.*.id' => [
                'required',
                'integer',
                'min:1',
            ],

            'items.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:50',
            ],

            'items.*.notes' => [
                'nullable',
                'string',
                'max:500',
            ],

            'items.*.extras' => [
                'sometimes',
                'array',
                'max:30',
            ],
            'items.*.components' => [
                'sometimes', 'array', 'max:50',
            ],
            'items.*.components.*.type' => [
                'required', Rule::in(['product', 'extra']),
            ],
            'items.*.components.*.id' => [
                'required', 'integer', 'min:1',
            ],
            'items.*.components.*.quantity' => [
                'required', 'integer', 'min:1', 'max:50',
            ],
            'items.*.extras.*.id' => [
                'required',
                'integer',
                'min:1',
            ],

            'items.*.extras.*.quantity' => [
                'required',
                'integer',
                'min:1',
                'max:50',
            ],
        ]);

        $quote = $orderService->quote($data);

        // No exponer detalles internos del cálculo.
        $items = array_map(
            fn (array $item) => [
                'type' => $item['type'],
                'id' => $item['id'],
                'name' => $item['name'],
                'quantity' => $item['quantity'],
                'unit_price' => number_format(
                    $item['base_cents'] / 100,
                    2,
                    '.',
                    ''
                ),
                'extras_unit_total' => number_format(
                    $item['extras_cents'] / 100,
                    2,
                    '.',
                    ''
                ),
                'line_total' => number_format(
                    $item['line_total_cents'] / 100,
                    2,
                    '.',
                    ''
                ),
            ],
            $quote['items']
        );

        unset($quote['items']);

        // Usar una clave separada del checkout HTML para no alterar su carrito.
        $token = $request->session()->get('json_checkout_token');

        if (
            !is_string($token)
            || !Str::isUuid($token)
            || Order::query()->where('checkout_token', $token)->exists()
        ) {
            $token = (string) Str::uuid();
            $request->session()->put('json_checkout_token', $token);
        }

        return response()->json([
            'checkout_token' => $token,
            'message' => 'Cotización calculada correctamente.',
            'quote' => $quote,
            'items' => $items,
        ]);
    }
}