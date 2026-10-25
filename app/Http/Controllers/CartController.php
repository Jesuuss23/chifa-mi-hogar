<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ComboService;
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

        $comboService = app(ComboService::class);

        foreach ($cart as $key => $item) {

            // ================================================
            // COMBOS PERSONALIZADOS
            // ================================================
            if (($item['type'] ?? null) === 'combo') {

                try {
                    $quantity = max(
                        1,
                        min(20, (int) ($item['quantity'] ?? 1))
                    );

                    $line = $comboService->buildLine([
                        'type' => 'combo',
                        'id' => $item['combo_id'] ?? null,
                        'quantity' => $quantity,
                        'components' => $item['components'] ?? [],
                    ]);

                    $combo = \App\Models\Combo::with('mediaAsset')
                        ->find($line['id']);

                    if (!$combo) {
                        continue;
                    }

                    $cleanCart[$key] = [
                        'type' => 'combo',
                        'combo_id' => $combo->id,
                        'quantity' => $quantity,
                        'components' => $item['components'] ?? [],
                    ];

                    $result[$key] = [
                        'key' => $key,
                        'type' => 'combo',
                        'combo' => $combo,
                        'product' => null,
                        'extras' => collect(),
                        'components' => $line['components'],
                        'quantity' => $quantity,
                        'unit_price' => (
                            $line['base_cents']
                            + $line['extras_cents']
                        ) / 100,
                        'total' => $line['line_total_cents'] / 100,
                    ];

                } catch (ValidationException $exception) {
                    // El combo dejó de estar disponible
                    // o cambió la configuración del catálogo.
                    continue;
                }

                continue;
            }

            // ================================================
            // PLATOS TRADICIONALES
            // ================================================
            $product = Product::with('category')
                ->where('is_available', true)
                ->whereHas('category', fn ($query) =>
                    $query->where('is_active', true)
                )
                ->find($item['product_id'] ?? null);

            if (!$product) {
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

            if ($extras->count() !== count($requestedIds)) {
                continue;
            }

            $quantity = max(
                1,
                min(20, (int) ($item['quantity'] ?? 1))
            );

            $unitPrice = (float) $product->price
                + $extras->sum(
                    fn ($extra) => (float) $extra->price
                );

            $cleanCart[$key] = [
                'product_id' => $product->id,
                'quantity' => $quantity,
                'extra_ids' => $requestedIds,
            ];

            $result[$key] = [
                'key' => $key,
                'type' => 'product',
                'product' => $product,
                'combo' => null,
                'extras' => $extras,
                'components' => [],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total' => round($unitPrice * $quantity, 2),
            ];
        }

        $request->session()->put('cart', $cleanCart);

        return $result;
    }
}