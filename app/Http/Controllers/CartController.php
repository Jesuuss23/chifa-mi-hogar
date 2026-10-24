<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->validatedCart($request);
        $subtotal = collect($cart)->sum('total');

        return view('storefront.cart', compact('cart', 'subtotal'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'extras' => ['nullable', 'array'],
            'extras.*' => ['integer', 'distinct'],
        ]);

        $product = Product::with('category')
            ->where('is_available', true)
            ->whereHas('category', fn ($query) =>
                $query->where('is_active', true)
            )
            ->findOrFail($data['product_id']);

        $extraIds = collect($data['extras'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->sort()
            ->values()
            ->all();

        $validExtras = $product->extras()
            ->where('is_available', true)
            ->whereIn('extras.id', $extraIds)
            ->get();

        if ($validExtras->count() !== count($extraIds)) {
            throw ValidationException::withMessages([
                'extras' => 'Uno o más adicionales no están disponibles para este plato.',
            ]);
        }

        $cart = $request->session()->get('cart', []);

        // Una combinación distinta de adicionales es una línea distinta.
        $key = hash('sha256', $product->id . ':' . implode(',', $extraIds));

        $currentQuantity = $cart[$key]['quantity'] ?? 0;

        if ($currentQuantity + $data['quantity'] > 20) {
            throw ValidationException::withMessages([
                'quantity' => 'El máximo por combinación es 20 unidades.',
            ]);
        }

        $cart[$key] = [
            'product_id' => $product->id,
            'quantity' => $currentQuantity + $data['quantity'],
            'extra_ids' => $extraIds,
        ];

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')
            ->with('success', 'Producto agregado al carrito.');
    }

    public function update(Request $request, string $key)
    {
        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $cart = $request->session()->get('cart', []);

        if (! isset($cart[$key])) {
            abort(404);
        }

        $cart[$key]['quantity'] = $data['quantity'];

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index');
    }

    public function destroy(Request $request, string $key)
    {
        $cart = $request->session()->get('cart', []);

        unset($cart[$key]);

        $request->session()->put('cart', $cart);

        return redirect()->route('cart.index')
            ->with('success', 'Producto retirado del carrito.');
    }

    private function validatedCart(Request $request): array
    {
        $cart = $request->session()->get('cart', []);
        $result = [];
        $cleanCart = [];

        foreach ($cart as $key => $item) {
            $product = Product::with('category')
                ->where('is_available', true)
                ->whereHas('category', fn ($query) =>
                    $query->where('is_active', true)
                )
                ->find($item['product_id'] ?? null);

            if (! $product) {
                continue;
            }

            $requestedIds = collect($item['extra_ids'] ?? [])
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->sort()
                ->values()
                ->all();

            $extras = $product->extras()
                ->where('is_available', true)
                ->whereIn('extras.id', $requestedIds)
                ->get();

            // Si cambió la disponibilidad de un adicional,
            // retiramos esta combinación del carrito.
            if ($extras->count() !== count($requestedIds)) {
                continue;
            }

            $quantity = max(1, min(20, (int) ($item['quantity'] ?? 1)));

            $unitPrice = (float) $product->price
                + $extras->sum(fn ($extra) => (float) $extra->price);

            $cleanCart[$key] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'extra_ids' => $requestedIds,
            ];

            $result[$key] = [
                'key' => $key,
                'product' => $product,
                'extras' => $extras,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => round($unitPrice * $quantity, 2),
            ];
        }

        $request->session()->put('cart', $cleanCart);

        return $result;
    }
}