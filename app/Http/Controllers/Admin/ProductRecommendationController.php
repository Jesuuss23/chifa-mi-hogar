<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductRecommendation;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CatalogSlugService;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductRecommendationController extends Controller
{
    public function index(Product $product)
    {
        $recommendations = ProductRecommendation::query()
            ->where('product_id', $product->id)
            ->with(['mediaAsset', 'extras'])
            ->orderBy('sort_order')
            ->paginate(12);

        return view('admin.recommendations.index', compact(
            'product',
            'recommendations'
        ));
    }

    public function create(Product $product)
    {
        $product->load('extras.extraCategory');

        return view('admin.recommendations.form', [
            'product' => $product,
            'recommendation' => new ProductRecommendation(),
            'extras' => $product->extras,
            'selectedExtras' => [],
        ]);
    }

    public function store(
        Request $request,
        Product $product,
        ImageService $images
    ) {
        $data = $this->validated($request, $product);

        $extraIds = $data['extras'] ?? [];

        unset($data['extras'], $data['image']);

        $data['product_id'] = $product->id;
        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('image')) {
            $data['media_asset_id'] = $images
                ->upload($request->file('image'))->id;
        }

        DB::transaction(function () use ($data, $extraIds) {
            $recommendation = app(CatalogSlugService::class)->create(ProductRecommendation::class, $data);
            $recommendation->extras()->sync($extraIds);
        });

        return redirect()
            ->route('admin.products.recommendations.index', $product)
            ->with('success', 'Recomendación creada correctamente.');
    }

    public function edit(
        Product $product,
        ProductRecommendation $recommendation
    ) {
        $this->ensureOwnership($product, $recommendation);

        $recommendation->load(['extras', 'mediaAsset']);
        $product->load('extras.extraCategory');

        return view('admin.recommendations.form', [
            'product' => $product,
            'recommendation' => $recommendation,
            'extras' => $product->extras,
            'selectedExtras' => $recommendation->extras->pluck('id')->all(),
        ]);
    }

    public function update(
        Request $request,
        Product $product,
        ProductRecommendation $recommendation,
        ImageService $images
    ) {
        $this->ensureOwnership($product, $recommendation);

        $data = $this->validated($request, $product, $recommendation);

        $extraIds = $data['extras'] ?? [];

        unset($data['extras'], $data['image']);

        $data['is_active'] = $request->boolean('is_active');

        if ($request->hasFile('image')) {
            $data['media_asset_id'] = $images
                ->upload($request->file('image'))->id;
        }

        DB::transaction(function () use ($recommendation, $data, $extraIds) {
            $recommendation->update($data);
            $recommendation->extras()->sync($extraIds);
        });

        return redirect()
            ->route('admin.products.recommendations.index', $product)
            ->with('success', 'Recomendación actualizada correctamente.');
    }

    public function destroy(
        Product $product,
        ProductRecommendation $recommendation
    ) {
        $this->ensureOwnership($product, $recommendation);

        $recommendation->delete();

        return redirect()
            ->route('admin.products.recommendations.index', $product)
            ->with('success', 'Recomendación eliminada correctamente.');
    }

    private function ensureOwnership(
        Product $product,
        ProductRecommendation $recommendation
    ): void {
        abort_unless(
            (string) $recommendation->product_id === (string) $product->id,
            404
        );
    }

    private function validated(
        Request $request,
        Product $product,
        ?ProductRecommendation $recommendation = null
    ): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'extras' => ['nullable', 'array'],
            'extras.*' => [
                'integer',
                'distinct',
                Rule::exists('extras', 'id')->whereNull('deleted_at'),
            ],
            'image' => [
                $recommendation?->media_asset_id
                    ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);

        $allowedIds = $product->extras()
            ->pluck('extras.id')
            ->map(fn ($id) => (int) $id)
            ->all();

        foreach ($data['extras'] ?? [] as $extraId) {
            if (!in_array((int) $extraId, $allowedIds, true)) {
                throw ValidationException::withMessages([
                    'extras' => 'La recomendación contiene adicionales no permitidos para este plato.',
                ]);
            }
        }

        return $data;
    }
}