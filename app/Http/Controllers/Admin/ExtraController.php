<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ExtraController extends Controller
{
    public function index(): View
    {
        $extras = Extra::query()
            ->with(['extraCategory', 'mediaAsset'])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(15);

        return view('admin.extras.index', compact('extras'));
    }

    public function create(): View
    {
        $categories = ExtraCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('admin.extras.form', [
            'extra' => new Extra(),
            'categories' => $categories,
        ]);
    }

    public function store(
        Request $request,
        ImageService $imageService
    ): RedirectResponse {
        $data = $this->validateData($request);
        unset($data['image']);

        $data['is_available'] = $request->boolean('is_available');

        try {
            if ($request->hasFile('image')) {
                $asset = $imageService->upload($request->file('image'));
                $data['media_asset_id'] = $asset->id;
            }

            Extra::create($data);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'general' => 'No se pudo crear el adicional.',
                ]);
        }

        return redirect()
            ->route('admin.extras.index')
            ->with('success', 'Adicional creado correctamente.');
    }

    public function edit(Extra $extra): View
    {
        $categories = ExtraCategory::query()
            ->where(function ($query) use ($extra) {
                $query->where('is_active', true)
                    ->orWhere('id', $extra->extra_category_id);
            })
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $extra->load(['extraCategory', 'mediaAsset']);

        return view('admin.extras.form', [
            'extra' => $extra,
            'categories' => $categories,
        ]);
    }

    public function update(
        Request $request,
        Extra $extra,
        ImageService $imageService
    ): RedirectResponse {
        $data = $this->validateData($request);
        unset($data['image']);

        $data['is_available'] = $request->boolean('is_available');

        try {
            if ($request->hasFile('image')) {
                $asset = $imageService->upload($request->file('image'));
                $data['media_asset_id'] = $asset->id;
            }

            $extra->update($data);
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors([
                    'general' => 'No se pudo actualizar el adicional.',
                ]);
        }

        return redirect()
            ->route('admin.extras.index')
            ->with('success', 'Adicional actualizado correctamente.');
    }

    public function destroy(Extra $extra): RedirectResponse
    {
        $extra->delete();

        return redirect()
            ->route('admin.extras.index')
            ->with('success', 'Adicional eliminado correctamente.');
    }

    private function validateData(Request $request): array
    {
        return $request->validate([
            'extra_category_id' => [
                'required',
                'integer',
                Rule::exists('extra_categories', 'id')
                    ->whereNull('deleted_at'),
            ],
            'name' => [
                'required',
                'string',
                'max:120',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'price' => [
                'required',
                'numeric',
                'min:0',
                'max:99999999.99',
                'decimal:0,2',
            ],
            'sort_order' => [
                'required',
                'integer',
                'min:0',
            ],
            'is_available' => [
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