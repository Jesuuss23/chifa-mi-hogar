<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;

class CartCheckoutService
{
    public function items(array $cart): array
    {
        $items = [];

        foreach ($cart as $entry) {
            if (!is_array($entry)) {
                throw ValidationException::withMessages([
                    'cart' => 'El carrito contiene un artículo inválido.',
                ]);
            }

            $quantity = (int) ($entry['quantity'] ?? 0);

            if ($quantity < 1 || $quantity > 20) {
                throw ValidationException::withMessages([
                    'cart' => 'Cantidad inválida en el carrito.',
                ]);
            }

            if (($entry['type'] ?? null) === 'combo') {
                $items[] = [
                    'type' => 'combo',
                    'id' => $entry['combo_id'] ?? null,
                    'quantity' => $quantity,
                    'components' => $entry['components'] ?? [],
                ];

                continue;
            }

            $extras = [];

            foreach (($entry['extra_ids'] ?? []) as $extraId) {
                $extras[] = [
                    'id' => $extraId,
                    'quantity' => 1,
                ];
            }

            $items[] = [
                'type' => 'product',
                'id' => $entry['product_id'] ?? null,
                'quantity' => $quantity,
                'extras' => $extras,
            ];
        }

        if (count($items) < 1 || count($items) > 30) {
            throw ValidationException::withMessages([
                'cart' => 'El carrito debe contener entre 1 y 30 artículos.',
            ]);
        }

        return $items;
    }
}
