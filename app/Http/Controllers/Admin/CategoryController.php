<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Services\ImageService;
use Illuminate\Http\Request;
use App\Services\CatalogSlugService;
use Illuminate\Validation\Rule;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('mediaAsset')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12);

        return view('admin.categories.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.form', [
            'category' => new Category(),
        ]);
    }

    public function store(Request $request, ImageService $images)
    {
        $data = $this->validated($request);

        $data['is_active'] = $request->boolean('is_active');

        unset($data['image']);

        if ($request->hasFile('image')) {
            $data['media_asset_id'] = $images
                ->upload($request->file('image'))->id;
        }

        app(CatalogSlugService::class)->create(Category::class, $data);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Categoría creada correctamente.');
    }

    public function edit(Category $category)
    {
        $category->load('mediaAsset');

        return view('admin.categories.form', compact('category'));
    }

    public function update(
        Request $request,
        Category $category,
        ImageService $images
    ) {
        $data = $this->validated($request, $category);

        $data['is_active'] = $request->boolean('is_active');

        unset($data['image']);

        if ($request->hasFile('image')) {
            $data['media_asset_id'] = $images
                ->upload($request->file('image'))->id;
        }

        $category->update($data);

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(Category $category)
    {
        if ($category->products()->exists()) {
            return back()->withErrors([
                'category' => 'No puedes eliminar una categoría que contiene platos.',
            ]);
        }

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }

    private function validated(Request $request, ?Category $category = null)
    {
        return $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('categories', 'name')
                    ->ignore($category?->id)
                    ->whereNull('deleted_at'),
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
            'image' => [
                $category?->media_asset_id ? 'nullable' : 'required',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);
    }
}