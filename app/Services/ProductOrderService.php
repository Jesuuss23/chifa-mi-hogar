<?php

namespace App\Services;

use App\Models\Product;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductOrderService
{
    public function suggestedOrder(): int
    {
        $maximum = Product::max('sort_order');
        if ($maximum === null) {
            return 0;
        }
        if ($maximum < 2147483647) {
            return (int) $maximum + 1;
        }
        $candidate = 0;
        foreach (Product::orderBy('sort_order')->pluck('sort_order') as $used) {
            if ((int) $used > $candidate) {
                break;
            }
            if ((int) $used === $candidate) {
                $candidate++;
            }
        }
        return $candidate;
    }

    public function save(array $data, array $extraIds, ?Product $product = null): Product
    {
        $order = filter_var($data['sort_order'] ?? null, FILTER_VALIDATE_INT);
        if ($order === false || $order < 0 || $order > 2147483647) {
            throw ValidationException::withMessages(['sort_order' => 'Ingresa un orden entre 0 y 2147483647.']);
        }

        $settings = StoreSetting::current();

        return DB::transaction(function () use ($settings, $product, $data, $order, $extraIds) {
            // One existing row serializes product saves, including inserts into an empty catalog.
            StoreSetting::whereKey($settings->id)->lockForUpdate()->firstOrFail();
            $current = $product
                ? Product::whereKey($product->id)->lockForUpdate()->firstOrFail()
                : null;

            // Preserve legacy ties when only editing other fields; never introduce a new tie.
            $moving = ! $current || $current->sort_order !== $order;
            if ($moving && ($occupied = Product::where('sort_order', $order)->orderBy('id')->first(['name']))) {
                throw ValidationException::withMessages([
                    'sort_order' => 'Número ocupado por el plato: '.$occupied->name.'. Elige un número libre.',
                ]);
            }

            if ($current) {
                $current->update($data);
            } else {
                $current = app(CatalogSlugService::class)->create(Product::class, $data);
            }
            $current->extras()->sync($extraIds);
            return $current;
        }, 3);
    }
}
