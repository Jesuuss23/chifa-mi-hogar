<?php

namespace App\Services;

use App\Models\Combo;
use Illuminate\Validation\ValidationException;

class ComboService
{
    public function buildLine(array $item): array
    {
        $id = (int) ($item['id'] ?? 0);
        $quantity = (int) ($item['quantity'] ?? 0);

        if ($id < 1 || $quantity < 1 || $quantity > 50) {
            throw ValidationException::withMessages([
                'items' => 'Combo o cantidad no válida.',
            ]);
        }

        $combo = Combo::query()
            ->with(['items.product.category', 'items.extra'])
            ->whereKey($id)
            ->where('is_available', true)
            ->first();

        if (!$combo || $combo->items->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'El combo no está disponible.',
            ]);
        }

        $requested = $item['components'] ?? [];

        if (!is_array($requested) || count($requested) > 50) {
            throw ValidationException::withMessages([
                'items' => 'Componentes del combo no válidos.',
            ]);
        }

        $selected = [];

        foreach ($requested as $component) {
            if (!is_array($component)) {
                throw ValidationException::withMessages([
                    'items' => 'Componente no válido.',
                ]);
            }

            $type = $component['type'] ?? null;
            $componentId = $component['id'] ?? null;
            $selectedQuantity = $component['quantity'] ?? null;

            if (
                !in_array($type, ['product', 'extra'], true)
                || filter_var($componentId, FILTER_VALIDATE_INT) === false
                || filter_var($selectedQuantity, FILTER_VALIDATE_INT) === false
                || (int) $componentId < 1
                || (int) $selectedQuantity < 1
                || (int) $selectedQuantity > 50
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Cantidad o componente inválido.',
                ]);
            }

            $key = $type . ':' . (int) $componentId;

            if (isset($selected[$key])) {
                throw ValidationException::withMessages([
                    'items' => 'No puedes repetir componentes.',
                ]);
            }

            $selected[$key] = (int) $selectedQuantity;
        }

        $components = [];
        $additionalCents = 0;

        foreach ($combo->items as $comboItem) {
            $product = $comboItem->product;
            $extra = $comboItem->extra;

            if (($product === null) === ($extra === null)) {
                throw ValidationException::withMessages([
                    'items' => 'El combo tiene un componente mal configurado.',
                ]);
            }

            $type = $product ? 'product' : 'extra';
            $catalogItem = $product ?: $extra;

            if (
                !$catalogItem->is_available
                || ($product && (!$product->category || !$product->category->is_active))
            ) {
                throw ValidationException::withMessages([
                    'items' => 'Uno de los componentes ya no está disponible.',
                ]);
            }

            $key = $type . ':' . $catalogItem->id;
            $included = (int) $comboItem->quantity;

            if ($included < 1 || $included > 50) {
                throw ValidationException::withMessages([
                    'items' => 'Cantidad incluida no válida.',
                ]);
            }

            $chosen = $selected[$key] ?? $included;

            if ($chosen < $included || $chosen > 50) {
                throw ValidationException::withMessages([
                    'items' => 'No puedes reducir los componentes incluidos.',
                ]);
            }

            unset($selected[$key]);

            $additional = $chosen - $included;
            $unitCents = $this->cents((string) $catalogItem->price);

            $additionalTotal = $additional * $unitCents;
            $additionalCents += $additionalTotal;

            $components[] = [
                'type' => $type,
                'id' => $catalogItem->id,
                'name' => $catalogItem->name,
                'included_quantity' => $included,
                'selected_quantity' => $chosen,
                'additional_quantity' => $additional,
                'unit_cents' => $unitCents,
                'additional_total_cents' => $additionalTotal,
            ];
        }

        if (!empty($selected)) {
            throw ValidationException::withMessages([
                'items' => 'Se enviaron componentes ajenos al combo.',
            ]);
        }

        $baseCents = $this->cents((string) $combo->price);

        if ($baseCents <= 0) {
            throw ValidationException::withMessages([
                'items' => 'El precio del combo no es válido.',
            ]);
        }

        return [
            'type' => 'combo',
            'id' => $combo->id,
            'name' => $combo->name,
            'description' => $combo->description,
            'quantity' => $quantity,
            'base_cents' => $baseCents,
            'extras_cents' => $additionalCents,
            'line_total_cents' => ($baseCents + $additionalCents) * $quantity,
            'extras' => [],
            'components' => $components,
            'notes' => $item['notes'] ?? null,
        ];
    }

    private function cents(string $amount): int
    {
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw ValidationException::withMessages([
                'items' => 'Precio inválido en el catálogo.',
            ]);
        }

        [$whole, $fraction] = array_pad(
            explode('.', $amount, 2),
            2,
            '0'
        );

        return ((int) $whole * 100)
            + (int) str_pad($fraction, 2, '0');
    }
}
