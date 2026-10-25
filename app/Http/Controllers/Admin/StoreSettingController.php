<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class StoreSettingController extends Controller
{
    public function edit(): View
    {
        $settings = StoreSetting::current()->load('paymentQr');

        return view('admin.settings.edit', compact('settings'));
    }

    public function update(
        Request $request,
        ImageService $images
    ): RedirectResponse {
        $settings = StoreSetting::current();

        $data = $request->validate([
            'yape_number' => ['nullable', 'regex:/^9[0-9]{8}$/'],
            'plin_number' => ['nullable', 'regex:/^9[0-9]{8}$/'],
            'payment_holder' => ['nullable', 'string', 'max:150'],

            'payment_qr' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:5120',
            ],

            'orders_whatsapp' => [
                'nullable',
                'regex:/^(51)?9[0-9]{8}$/',
            ],

            'free_delivery_km' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],
            'google_reviews_url' => [
                'nullable',
                'url:http,https',
                'max:500',
            ],
            'additional_cost_per_km' => [
                'required',
                'numeric',
                'min:0',
                'max:999999.99',
            ],

            'kitchen_latitude' => [
                'required',
                'numeric',
                'between:-90,90',
            ],

            'kitchen_longitude' => [
                'required',
                'numeric',
                'between:-180,180',
            ],

            'accepting_orders' => [
                'required',
                Rule::in(['0', '1']),
            ],

            'closed_message' => [
                'nullable',
                'string',
                'max:1000',
            ],

            'whatsapp_group_url' => [
                'nullable',
                'url',
                'max:2000',
                'regex:/^https:\/\/chat\.whatsapp\.com\//i',
            ],
        ], [
            'yape_number.regex' =>
                'El número de Yape debe tener 9 dígitos y comenzar con 9.',

            'plin_number.regex' =>
                'El número de Plin debe tener 9 dígitos y comenzar con 9.',

            'orders_whatsapp.regex' =>
                'Ingresa un celular peruano válido, con o sin el código 51.',

            'payment_qr.max' =>
                'El código QR no debe superar los 5 MB.',

            'kitchen_latitude.between' =>
                'La latitud debe estar entre -90 y 90.',

            'kitchen_longitude.between' =>
                'La longitud debe estar entre -180 y 180.',

            'whatsapp_group_url.regex' =>
                'Ingresa un enlace válido de comunidad o grupo de WhatsApp.',
        ]);

        unset($data['payment_qr']);

        $data['accepting_orders'] =
            (bool) $data['accepting_orders'];

        $data['closed_message'] =
            $data['closed_message']
            ?: 'Por el momento no estamos recibiendo pedidos.';

        // Normalizar número comercial con prefijo de Perú.
        if (!empty($data['orders_whatsapp'])) {
            $number = $data['orders_whatsapp'];

            $data['orders_whatsapp'] =
                str_starts_with($number, '51')
                    ? $number
                    : '51' . $number;
        }

        // Subir el nuevo QR únicamente si se seleccionó un archivo.
        if ($request->hasFile('payment_qr')) {
            $asset = $images->upload(
                $request->file('payment_qr')
            );

            $data['payment_qr_media_asset_id'] = $asset->id;
        }

        DB::transaction(function () use ($settings, $data) {
            $settings->update($data);
        });

        return redirect()
            ->route('admin.settings.edit')
            ->with('success', 'Configuración guardada correctamente.');
    }
}