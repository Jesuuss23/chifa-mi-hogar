<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Combo;
use App\Models\Product;
use App\Models\PromotionalBanner;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class PromotionalBannerController extends Controller
{
    public function index()
    {
        $banners = PromotionalBanner::with(['mediaAsset', 'product', 'combo'])
            ->orderBy('sort_order')->orderBy('id')->paginate(15);

        return view('admin.banners.index', compact('banners'));
    }

    public function create()
    {
        return $this->form(new PromotionalBanner);
    }

    public function edit(PromotionalBanner $banner)
    {
        return $this->form($banner);
    }

    private function form(PromotionalBanner $banner)
    {
        $banner->load(['mediaAsset', 'mobileMediaAsset']);

        return view('admin.banners.form', [
            'banner' => $banner,
            'products' => Product::orderBy('name')->get(['id', 'name']),
            'combos' => Combo::orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(Request $request, ImageService $images)
    {
        return $this->save($request, new PromotionalBanner, $images);
    }

    public function update(Request $request, PromotionalBanner $banner, ImageService $images)
    {
        return $this->save($request, $banner, $images);
    }

    private function save(Request $request, PromotionalBanner $banner, ImageService $images)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'target_type' => ['required', Rule::in(['product', 'combo'])],
            'product_id' => ['exclude_unless:target_type,product', 'required', 'integer', Rule::exists('products', 'id')->whereNull('deleted_at')],
            'combo_id' => ['exclude_unless:target_type,combo', 'required', 'integer', Rule::exists('combos', 'id')->whereNull('deleted_at')],
            'sort_order' => ['required', 'integer', 'between:0,10000'],
            'is_active' => ['required', 'boolean'],
            'image' => [$banner->exists ? 'nullable' : 'required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'mobile_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=8000,max_height=8000'],
            'remove_mobile_image' => ['nullable', 'boolean'],
        ]);
        $created = [];
        try {
            // Existing assets are retained; a failed save only removes files uploaded by this request.
            DB::transaction(function () use ($request, $banner, $images, $data, &$created) {
                $attributes = [
                    'title' => $data['title'], 'sort_order' => $data['sort_order'],
                    'is_active' => $data['is_active'],
                    'product_id' => $data['target_type'] === 'product' ? $data['product_id'] : null,
                    'combo_id' => $data['target_type'] === 'combo' ? $data['combo_id'] : null,
                ];
                foreach (['image' => 'media_asset_id', 'mobile_image' => 'mobile_media_asset_id'] as $field => $column) {
                    if ($request->hasFile($field)) {
                        $asset = $images->upload($request->file($field));
                        $created[] = $asset;
                        $attributes[$column] = $asset->id;
                    }
                }
                if (! $request->hasFile('mobile_image') && $request->boolean('remove_mobile_image')) {
                    $attributes['mobile_media_asset_id'] = null;
                }
                $banner->fill($attributes)->save();
            });
        } catch (Throwable $exception) {
            foreach ($created as $asset) {
                Storage::disk('public')->delete([$asset->thumbnail_path, $asset->card_path, $asset->detail_path]);
                Storage::disk('local')->delete($asset->original_path);
            }
            throw $exception;
        }

        return redirect()->route('admin.banners.index')->with('success', 'Banner guardado.');
    }

    public function destroy(PromotionalBanner $banner)
    {
        $banner->delete();

        return redirect()->route('admin.banners.index')->with('success', 'Banner eliminado.');
    }
}
