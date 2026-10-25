<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\ComboItem;
use App\Models\Extra;
use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use App\Services\ImageService;

class ComboController extends Controller
{
    public function index()
    {
        $combos = Combo::with(['items.product', 'items.extra'])
            ->orderBy('sort_order')
            ->latest('id')
            ->paginate(15);

        return view('admin.combos.index', compact('combos'));
    }

    public function create()
    {
        return view('admin.combos.form', [
            'combo' => new Combo(),

            'products' => Product::where('is_available', true)
                ->whereHas('category', fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get(),

            'extras' => Extra::where('is_available', true)
                ->orderBy('name')
                ->get(),

            'selectedRecommendations' => [],
        ]);
    }

    public function store(Request $request, ImageService $images)
    {
        $data = $this->validateCombo($request);

        $mediaAssetId = null;

        if ($request->hasFile('image')) {
            $mediaAssetId = $images
                ->upload($request->file('image'))
                ->id;
        }

        DB::transaction(function () use ($data, $mediaAssetId) {
            $combo = Combo::create([
                'name' => $data['name'],
                'slug' => Str::slug($data['name']) . '-' . Str::lower(Str::random(6)),
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'media_asset_id' => $mediaAssetId,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_available' => true,
                'show_in_menu' => $data['show_in_menu'] ?? false,
            ]);

            $this->saveItems($combo, $data['items']);

            $combo->recommendedForProducts()->sync(
                $data['recommended_product_ids'] ?? []
            );
        });

        return redirect()
            ->route('admin.combos.index')
            ->with('success', 'Combo creado correctamente.');
    }

    public function edit(Combo $combo)
    {
        $combo->load([
            'items',
            'mediaAsset',
            'recommendedForProducts',
        ]);

        return view('admin.combos.form', [
            'combo' => $combo,

            'products' => Product::where('is_available', true)
                ->whereHas('category', fn ($q) => $q->where('is_active', true))
                ->orderBy('name')
                ->get(),

            'extras' => Extra::where('is_available', true)
                ->orderBy('name')
                ->get(),

            'selectedRecommendations' => $combo
                ->recommendedForProducts
                ->pluck('id')
                ->all(),
        ]);
    }

    public function destroy(Combo $combo)
    {
        $combo->update([
            'is_available' => false,
            'show_in_menu' => false,
        ]);

        $combo->delete();

        return redirect()
            ->route('admin.combos.index')
            ->with('success', 'Combo retirado correctamente.');
    }

    private function validateCombo(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0.01', 'decimal:0,2'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'show_in_menu' => ['nullable', 'boolean'],
            'is_available' => ['nullable', 'boolean'],

            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer'],
            'items.*.extra_id' => ['nullable', 'integer'],
            'items.*.quantity' => ['required', 'integer', 'between:1,20'],
            'image' => [
            'nullable',
            'image',
            'mimes:jpg,jpeg,png,webp',
            'max:5120',
        ],

        'recommended_product_ids' => [
            'nullable',
            'array',
        ],

        'recommended_product_ids.*' => [
            'integer',
            'distinct',
            Rule::exists('products', 'id')
                ->whereNull('deleted_at'),
        ],
        ]);

        $seen = [];

        foreach ($data['items'] as $index => $item) {
            $productId = $item['product_id'] ?? null;
            $extraId = $item['extra_id'] ?? null;

            $hasProduct = $productId !== null && $productId !== '';
            $hasExtra = $extraId !== null && $extraId !== '';

            if ($hasProduct === $hasExtra) {
                throw ValidationException::withMessages([
                    "items.$index" =>
                        'Cada componente debe ser un plato o un adicional, no ambos.',
                ]);
            }

            if ($hasProduct) {
                $exists = Product::query()
                    ->whereKey($productId)
                    ->where('is_available', true)
                    ->whereHas('category', function ($query) {
                        $query->where('is_active', true);
                    })
                    ->exists();

                if (! $exists) {
                    throw ValidationException::withMessages([
                        "items.$index" =>
                            'El plato seleccionado no está disponible.',
                    ]);
                }

                $key = 'product:' . $productId;
            } else {
                $exists = Extra::query()
                    ->whereKey($extraId)
                    ->where('is_available', true)
                    ->exists();

                if (! $exists) {
                    throw ValidationException::withMessages([
                        "items.$index" =>
                            'El adicional seleccionado no está disponible.',
                    ]);
                }

                $key = 'extra:' . $extraId;
            }

            if (isset($seen[$key])) {
                throw ValidationException::withMessages([
                    "items.$index" =>
                        'Este componente ya fue agregado. Modifica su cantidad.',
                ]);
            }

            $seen[$key] = true;
        }

        return $data;
    }

    private function saveItems(Combo $combo, array $items): void
    {
        foreach ($items as $item) {
            ComboItem::create([
                'combo_id' => $combo->id,
                'product_id' => $item['product_id'] ?? null,
                'extra_id' => $item['extra_id'] ?? null,
                'quantity' => $item['quantity'],
            ]);
        }
    }
    public function update(Request $request, Combo $combo, ImageService $images)
    {
        $data = $this->validateCombo($request);

        $mediaAssetId = $combo->media_asset_id;

        if ($request->hasFile('image')) {
            $mediaAssetId = $images
                ->upload($request->file('image'))
                ->id;
        }

        DB::transaction(function () use ($combo, $data, $mediaAssetId) {
            $combo->update([
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'price' => $data['price'],
                'media_asset_id' => $mediaAssetId,
                'sort_order' => $data['sort_order'] ?? 0,
                'show_in_menu' => $data['show_in_menu'] ?? false,
                'is_available' => $data['is_available'] ?? false,
            ]);

            $combo->items()->delete();

            $this->saveItems($combo, $data['items']);

            $combo->recommendedForProducts()->sync(
                $data['recommended_product_ids'] ?? []
            );
        });

        return redirect()
            ->route('admin.combos.index')
            ->with('success', 'Combo actualizado correctamente.');
    }
}