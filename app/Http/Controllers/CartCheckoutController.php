<?php

namespace App\Http\Controllers;

use App\Services\CartCheckoutService;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;
use App\Models\Order;
use App\Models\StoreSetting;

class CartCheckoutController extends Controller
{
    public function show(Request $request, CartCheckoutService $cart)
    {
        if (empty($request->session()->get('cart', []))) {
            return redirect()->route('cart.index');
        }

        $cart->items($request->session()->get('cart', []));
        $request->session()->put(
            'checkout_token',
            $request->session()->get('checkout_token') ?: (string) Str::uuid()
        );
        return view('storefront.checkout');
        
    }

    public function quote(
        Request $request,
        CartCheckoutService $cart,
        OrderService $orders
    ) {
        $data = $this->validatedData($request);

        $data['items'] = $cart->items(
            $request->session()->get('cart', [])
        );

        $quote = $orders->quote($data);

        return back()
            ->withInput()
            ->with('checkout_quote', $quote);
    }

    public function confirm(
        Request $request,
        CartCheckoutService $cart,
        OrderService $orders
    ) {
        // 1. Validar el token del checkout.
        $request->validate([
            'checkout_token' => ['required', 'uuid'],
        ]);

        $token = (string) $request->input('checkout_token');

        $sessionToken = (string) $request->session()
            ->get('checkout_token', '');

        if (
            $sessionToken === '' ||
            !hash_equals($sessionToken, $token)
        ) {
            abort(419, 'La sesión de compra ha expirado.');
        }

        // 2. Verificar si este pedido ya fue registrado.
        $existingOrder = \App\Models\Order::query()
            ->where('checkout_token', $token)
            ->first();

        if ($existingOrder) {

            $request->session()->put(
                'last_confirmed_order_id',
                $existingOrder->id
            );

            $request->session()->forget([
                'cart',
                'checkout_token',
            ]);
        }

        // 3. Validar los datos del cliente, entrega y pago.
        $data = $this->validatedData($request, true);

        // 4. Asociar el token al pedido.
        $data['checkout_token'] = $token;

        // 5. Obtener los productos y combos del carrito.
        $data['items'] = $cart->items(
            $request->session()->get('cart', [])
        );

        // 6. Recalcular los precios desde el servidor.
        // Incluye productos, extras, combos y delivery.
        $orders->quote($data);

        // 7. Registrar el pedido.
        // OrderService debe proteger el token dentro
        // de su transacción para evitar duplicados.
        $order = $orders->create($data);

        // 8. Guardar la referencia del pedido confirmado.
        $request->session()->put(
            'last_confirmed_order_id',
            $order->id
        );

        // 9. Limpiar el carrito y el token utilizado.
        $request->session()->forget([
            'cart',
            'checkout_token',
        ]);

        // 10. Mostrar la pantalla de confirmación.
        return redirect()
            ->route('cart.checkout.success');
    }

    public function success(Request $request)
    {
        $orderId = $request->session()->get('last_confirmed_order_id');

        if (!$orderId) {
            return redirect()->route('cart.index');
        }

        $order = Order::query()->find($orderId);
        if (!$order->tracking_token) {
            $order->tracking_token = (string) Str::uuid();
            $order->save();
        }
        if (!$order) {
            return redirect()->route('cart.index');
        }

        $settings = StoreSetting::current();

        $phone = preg_replace(
            '/\D+/',
            '',
            (string) ($settings->orders_whatsapp ?? '')
        );

        // Normalizar números peruanos de 9 dígitos.
        if (preg_match('/^9[0-9]{8}$/', $phone)) {
            $phone = '51' . $phone;
        }

        $whatsappUrl = null;

        if (preg_match('/^519[0-9]{8}$/', $phone)) {
            $message = "Hola, Chifa Mi Hogar.\n"
                . "Quiero enviar el comprobante de mi pedido.\n\n"
                . "Pedido: {$order->code}\n"
                . "Total: S/ {$order->total}\n"
                . "Método de pago: " . strtoupper($order->payment_method) . "\n\n"
                . "Adjuntaré mi comprobante de pago.";

            $whatsappUrl = 'https://wa.me/'
                . $phone
                . '?text='
                . rawurlencode($message);
        }

        return view('storefront.checkout-success', compact(
            'order',
            'whatsappUrl'
        ));
    }
    private function validatedData(Request $request, bool $confirm = false): array
    {
        $rules = [
            'delivery_type' => ['required', Rule::in(['delivery']),],
            'delivery_address' => ['required_if:delivery_type,delivery', 'nullable', 'string', 'max:500'],
            'delivery_reference' => ['nullable', 'string', 'max:500'],
            'delivery_latitude' => ['required_if:delivery_type,delivery', 'nullable', 'numeric', 'between:-90,90'],
            'delivery_longitude' => ['required_if:delivery_type,delivery', 'nullable', 'numeric', 'between:-180,180'],
            'payment_method' => ['required', Rule::in(['cash', 'yape']),],
            'cash_payment_type' => ['required_if:payment_method,cash', 'nullable', Rule::in(['exact', 'change'])],
            'cash_received_amount' => ['required_if:cash_payment_type,change', 'nullable', 'numeric', 'decimal:0,2', 'min:0'],
            'customer_notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($confirm) {
            $rules += [
                'customer_name' => ['required', 'string', 'max:150'],
                'customer_phone' => ['required', 'string', 'regex:/^9[0-9]{8}$/'],
                'customer_email' => ['nullable', 'email', 'max:150'],
            ];
        }

        return Validator::make($request->all(), $rules)->validate();
    }

    public function status(Request $request)
    {
        $orderId = $request->session()->get('last_confirmed_order_id');

        if (!$orderId) {
            return response()->json(['message' => 'Pedido no disponible'], 403);
        }

        $order = \App\Models\Order::query()
            ->whereKey($orderId)
            ->first();

        if (!$order) {
            return response()->json(['message' => 'Pedido no encontrado'], 404);
        }

        return response()->json([
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'updated_at' => $order->updated_at?->toIso8601String(),
        ])->header('Cache-Control', 'no-store, private');
    }
}
