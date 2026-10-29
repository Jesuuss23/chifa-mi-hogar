<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Extra;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\CatalogSlugService;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index()
    {
        $products = Product::with(['category', 'mediaAsset'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.products.index', compact('products'));
    }

    public function create()
    {
        return view('admin.products.form', [
            'product' => new Product(),
            'categories' => Category::where('is_active', true)
                ->orderBy('sort_order')
                ->get(),
            'extras' => Extra::where('is_available', true)
                ->orderBy('sort_order')
                ->get(),
            'selectedExtras' => [],
        ]);
    }

    public function store(Request $request, ImageService $images)
    {
        $data = $this->validated($request);

        $extraIds = $data['extras'] ?? [];
        unset($data['extras'], $data['image']);

        $data['is_available'] = $request->boolean('is_available');
        $data['is_featured'] = $request->boolean('is_featured');

        if ($request->hasFile('image')) {
            $data['media_asset_id'] = $images
                ->upload($request->file('image'))->id;
        }

        DB::transaction(function () use ($data, $extraIds) {
            $product = app(CatalogSlugService::class)->create(Product::class, $data);
            $product->extras()->sync($extraIds);
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Plato creado correctamente.');
    }

    public function edit(Product $product)
    {
        $product->load(['extras', 'mediaAsset']);

        return view('admin.products.form', [
            'product' => $product,
            'categories' => Category::where('is_active', true)
                ->orWhere('id', $product->category_id)
                ->orderBy('sort_order')
                ->get(),
            'extras' => Extra::where('is_available', true)
                ->orWhereHas('products', fn ($q) =>
                    $q->whereKey($product->id)
                )
                ->orderBy('sort_order')
                ->get(),
            'selectedExtras' => $product->extras->pluck('id')->all(),
        ]);
    }

    public function update(
        Request $request,
        Product $product,
        ImageService $images
    ) {
        $data = $this->validated($request, $product);

        $extraIds = $data['extras'] ?? [];
        unset($data['extras'], $data['image']);

        $data['is_available'] = $request->boolean('is_available');
        $data['is_featured'] = $request->boolean('is_featured');

        if ($request->hasFile('image')) {
            $data['media_asset_id'] = $images
                ->upload($request->file('image'))->id;
        }

        DB::transaction(function () use ($product, $data, $extraIds) {
            $product->update($data);
            $product->extras()->sync($extraIds);
        });

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Plato actualizado correctamente.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()
            ->route('admin.products.index')
            ->with('success', 'Plato eliminado correctamente.');
    }

    private function validated(Request $request, ?Product $product = null)
    {
        return $request->validate([
            'category_id' => [
                'required',
                Rule::exists('categories', 'id')
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_available' => ['sometimes', 'boolean'],
            'is_featured' => ['sometimes', 'boolean'],
            'extras' => ['nullable', 'array'],
            'extras.*' => [
                'integer',
                'distinct',
                Rule::exists('extras', 'id')
                    ->whereNull('deleted_at'),
            ],
            'image' => [
                $product?->media_asset_id || $product?->image_path
                    ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);
    }
}