<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExtraCategory;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ExtraCategoryController extends Controller
{
    public function index(): View
    {
        $categories = ExtraCategory::query()
            ->with('mediaAsset')
            ->withCount('extras')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(12);

        return view('admin.extra-categories.index', compact('categories'));
    }

    public function create(): View
    {
        return view('admin.extra-categories.form', [
            'category' => new ExtraCategory(),
        ]);
    }

    public function store(
        Request $request,
        ImageService $imageService
    ): RedirectResponse {
        $data = $this->validateData($request);
        unset($data['image']);

        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        try {
            if ($request->hasFile('image')) {
                $asset = $imageService->upload($request->file('image'));
                $data['media_asset_id'] = $asset->id;
            }

            ExtraCategory::create($data);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'general' => 'No se pudo crear la categoría.',
                ]);
        }

        return redirect()
            ->route('admin.extra-categories.index')
            ->with('success', 'Categoría creada correctamente.');
    }

    public function edit(ExtraCategory $extraCategory): View
    {
        $extraCategory->load('mediaAsset');

        return view('admin.extra-categories.form', [
            'category' => $extraCategory,
        ]);
    }

    public function update(
        Request $request,
        ExtraCategory $extraCategory,
        ImageService $imageService
    ): RedirectResponse {
        $data = $this->validateData($request, $extraCategory);
        unset($data['image']);

        $data['slug'] = Str::slug($data['name']);
        $data['is_active'] = $request->boolean('is_active');

        try {
            if ($request->hasFile('image')) {
                $asset = $imageService->upload($request->file('image'));
                $data['media_asset_id'] = $asset->id;
            }

            $extraCategory->update($data);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'general' => 'No se pudo actualizar la categoría.',
                ]);
        }

        return redirect()
            ->route('admin.extra-categories.index')
            ->with('success', 'Categoría actualizada correctamente.');
    }

    public function destroy(
        ExtraCategory $extraCategory
    ): RedirectResponse {
        if ($extraCategory->extras()->exists()) {
            return back()->withErrors([
                'general' => 'Esta categoría contiene adicionales. Muévelos a otra categoría antes de eliminarla.',
            ]);
        }

        $extraCategory->delete();

        return redirect()
            ->route('admin.extra-categories.index')
            ->with('success', 'Categoría eliminada correctamente.');
    }

    private function validateData(
        Request $request,
        ?ExtraCategory $category = null
    ): array {
        return $request->validate([
            'name' => [
                'required',
                'string',
                'max:120',
                Rule::unique('extra_categories', 'name')
                    ->ignore($category?->id)
                    ->whereNull('deleted_at'),
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'sort_order' => [
                'required',
                'integer',
                'min:0',
            ],
            'is_active' => [
                'sometimes',
                'boolean',
            ],
            'image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],
        ]);
    }
}