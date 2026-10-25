<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Services\ComboService;
use Illuminate\Http\Request;

class ComboCartController extends Controller
{
    public function store(Request $request, ComboService $combos)
    {
        $data = $request->validate([
            'combo_id' => ['required', 'integer', 'exists:combos,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'components' => ['nullable', 'array', 'max:50'],
            'components.*.type' => ['required', 'in:product,extra'],
            'components.*.id' => ['required', 'integer', 'min:1'],
            'components.*.quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);

        $combo = Combo::query()
            ->where('is_available', true)
            ->findOrFail($data['combo_id']);

        $components = $data['components'] ?? [];

        // Validar cantidades, disponibilidad y precios en el servidor.
        $combos->buildLine([
            'type' => 'combo',
            'id' => $combo->id,
            'quantity' => $data['quantity'],
            'components' => $components,
        ]);

        $normalized = collect($components)
            ->sortBy(fn ($item) => $item['type'] . ':' . $item['id'])
            ->values()
            ->all();

        $key = hash('sha256', json_encode([
            'type' => 'combo',
            'id' => $combo->id,
            'components' => $normalized,
        ]));

        $cart = $request->session()->get('cart', []);

        $current = (int) ($cart[$key]['quantity'] ?? 0);
        $quantity = $current + $data['quantity'];

        if ($quantity > 20) {
            return back()->withErrors([
                'quantity' => 'El máximo por combinación es 20 unidades.',
            ]);
        }

        $cart[$key] = [
            'type' => 'combo',
            'combo_id' => $combo->id,
            'quantity' => $quantity,
            'components' => $normalized,
        ];

        $request->session()->put('cart', $cart);

        return redirect()
            ->route('cart.index')
            ->with('success', 'Combo agregado al carrito.');
    }
}
