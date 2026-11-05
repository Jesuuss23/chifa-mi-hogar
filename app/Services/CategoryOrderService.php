<?php

namespace App\Services;

use App\Models\Category;
use App\Models\StoreSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryOrderService
{
    public function suggestedOrder(): int
    {
        $maximum = Category::max('sort_order');
        if ($maximum === null) {
            return 0;
        }
        if ($maximum < 2147483647) {
            return (int) $maximum + 1;
        }
        $candidate = 0;
        foreach (Category::orderBy('sort_order')->pluck('sort_order') as $used) {
            if ((int) $used > $candidate) {
                break;
            }
            if ((int) $used === $candidate) {
                $candidate++;
            }
        }
        return $candidate;
    }

    public function save(array $data, ?Category $category = null): Category
    {
        $order = filter_var($data['sort_order'] ?? null, FILTER_VALIDATE_INT);
        if ($order === false || $order < 0 || $order > 2147483647) {
            throw ValidationException::withMessages(['sort_order' => 'Ingresa un orden entre 0 y 2147483647.']);
        }

        $settings = StoreSetting::current();

        return DB::transaction(function () use ($settings, $category, $data, $order) {
            // One existing row serializes category saves, including inserts into an empty catalog.
            StoreSetting::whereKey($settings->id)->lockForUpdate()->firstOrFail();
            $current = $category
                ? Category::whereKey($category->id)->lockForUpdate()->firstOrFail()
                : null;

            // Preserve legacy ties when only editing other fields; never introduce a new tie.
            $moving = ! $current || $current->sort_order !== $order;
            if ($moving && Category::where('sort_order', $order)->exists()) {
                throw ValidationException::withMessages([
                    'sort_order' => 'Ese orden ya pertenece a otra categoría, incluso si está inactiva. Elige un número libre.',
                ]);
            }

            if ($current) {
                $current->update($data);
                return $current;
            }

            return app(CatalogSlugService::class)->create(Category::class, $data);
        }, 3);
    }
}
