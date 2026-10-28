<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class BrandSettingController extends Controller
{
    public function update(Request $request, ImageService $images): RedirectResponse
    {
        $data = $request->validate([
            'business_owner' => ['required', 'string', 'max:150'],
            'privacy_email' => ['required', 'email', 'max:255'],
            'website_url' => ['required', 'url:https', 'max:500'],
            'business_ruc' => ['nullable', 'regex:/^[0-9]{11}$/'],
            'logo' => [
                'nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
                'dimensions:max_width=4096,max_height=4096',
            ],
        ], [
            'business_ruc.regex' => 'Ingresa 11 dígitos o deja el RUC pendiente.',
            'logo.max' => 'El logo no debe superar los 2 MB.',
            'logo.dimensions' => 'El logo no debe superar 4096 píxeles de ancho o alto.',
        ]);
        unset($data['logo']);

        $settings = StoreSetting::current();
        $asset = null;

        try {
            DB::transaction(function () use ($request, $images, $settings, &$data, &$asset) {
                if ($request->hasFile('logo')) {
                    $asset = $images->upload($request->file('logo'));
                    $data['logo_media_asset_id'] = $asset->id;
                }

                $settings->update($data);
            });
        } catch (Throwable $exception) {
            // Limpiar solo el archivo nuevo; el logo anterior se conserva.
            if ($asset !== null) {
                Storage::disk('local')->delete($asset->original_path);
                Storage::disk('public')->delete([
                    $asset->thumbnail_path, $asset->card_path, $asset->detail_path,
                ]);
            }
            throw $exception;
        }

        return redirect()->route('admin.settings.edit')
            ->with('success', 'Identidad del negocio guardada correctamente.');
    }
}
