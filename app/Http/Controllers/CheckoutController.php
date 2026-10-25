<?php

namespace App\Http\Controllers;

use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CheckoutController extends Controller
{
    public function store(
        Request $request,
        OrderService $orders
    ): JsonResponse {
        $data = $request->validate([
            'customer_name' => ['required', 'string', 'max:150'],
            'customer_phone' => [
                'required', 'string', 'regex:/^9[0-9]{8}$/',
            ],
            'customer_email' => [
                'nullable', 'email', 'max:150',
            ],
            'delivery_type' => [
                'required',
                Rule::in(['pickup', 'delivery']),
            ],

            'delivery_address' => [
                'required_if:delivery_type,delivery',
                'nullable',
                'string',
                'max:500',
            ],

            'delivery_reference' => [
                'nullable',
                'string',
                'max:500',
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
            'customer_notes' => [
                'nullable', 'string', 'max:1000',
            ],
            'items' => [
                'required', 'array', 'min:1', 'max:30',
            ],
            'items.*.type' => [
                'required', Rule::in(['product', 'recommendation', 'combo']),
            ],
            'items.*.id' => [
                'required', 'integer', 'min:1',
            ],
            'items.*.quantity' => [
                'required', 'integer', 'min:1', 'max:50',
            ],
            'items.*.notes' => [
                'nullable', 'string', 'max:500',
            ],
            'items.*.extras' => [
                'sometimes', 'array', 'max:30',
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
                'required', 'integer', 'min:1',
            ],
            'items.*.extras.*.quantity' => [
                'required', 'integer', 'min:1', 'max:50',
            ],
        ]);

        $order = $orders->create($data);

        return response()->json([
            'message' => 'Pedido registrado. Pago pendiente de verificación.',
            'order' => [
                'code' => $order->code,
                'status' => $order->status,
                'payment_status' => $order->payment_status,
                'subtotal' => $order->subtotal,
                'delivery_fee' => $order->delivery_fee,
                'total' => $order->total,
            ],
        ], 201);
    }
}