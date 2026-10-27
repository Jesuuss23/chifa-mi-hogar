<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\Request;

class StorefrontController extends Controller
{
    public function index(Request $request)
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $selectedCategory = null;

        if ($request->filled('categoria')) {
            $selectedCategory = $categories
                ->firstWhere('slug', $request->query('categoria'));

            if (! $selectedCategory) {
                abort(404);
            }
        }

        $products = Product::query()
            ->with(['category', 'mediaAsset'])
            ->where('is_available', true)
            ->whereHas('category', function ($query) {
                $query->where('is_active', true);
            })
            ->when($selectedCategory, function ($query) use ($selectedCategory) {
                $query->where('category_id', $selectedCategory->id);
            })
            ->orderByDesc('is_featured')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString();

        return view('storefront.index', compact(
            'categories',
            'products',
            'selectedCategory'
        ));
    }

    public function show(Product $product)
    {
        if (
            ! $product->is_available ||
            ! $product->category ||
            ! $product->category->is_active
        ) {
            abort(404);
        }

        $product->load([
            'category',
            'mediaAsset',

            // Adicionales disponibles para el plato.
            'extras' => function ($query) {
                $query
                    ->where('is_available', true)
                    ->with([
                        'mediaAsset',
                        'extraCategory',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name');
            },

            // Recomendaciones asociadas a este plato.
            'recommendations' => function ($query) {
                $query
                    ->where('is_active', true)
                    ->with([
                        'mediaAsset',
                        'extras.mediaAsset',
                        'extras.extraCategory',
                    ])
                    ->orderBy('sort_order')
                    ->orderBy('name');
            },
        ]);

        return view('storefront.show', compact('product'));
    }
}