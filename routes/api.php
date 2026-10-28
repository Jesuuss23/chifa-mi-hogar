<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\MetaWhatsappWebhookController;

Route::get(
    '/webhooks/meta/whatsapp',
    [MetaWhatsappWebhookController::class, 'verify']
);

Route::post(
    '/webhooks/meta/whatsapp',
    [MetaWhatsappWebhookController::class, 'receive']
);

// Imagen de marca pública y sin sesión. Nunca recibe rutas de archivos del visitante.
Route::get('/brand/share-image.jpg', \App\Http\Controllers\BrandShareImageController::class)
    ->middleware('throttle:60,1')->name('brand.share-image');
