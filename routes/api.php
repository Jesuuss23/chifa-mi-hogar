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


Route::prefix('v1/admin')->middleware(\App\Http\Middleware\AdminMobileResponse::class)
    ->group(function () {
        Route::post('auth/login', [\App\Http\Controllers\Api\Admin\MobileAuthController::class, 'login'])
            ->middleware('throttle:5,1');
        Route::middleware(['auth:sanctum', \App\Http\Middleware\EnsureAdminMobileToken::class, 'throttle:120,1'])
            ->group(function () {
                Route::get('auth/me', [\App\Http\Controllers\Api\Admin\MobileAuthController::class, 'me']);
                Route::post('auth/logout', [\App\Http\Controllers\Api\Admin\MobileAuthController::class, 'logout']);
                Route::post('push/register', [\App\Http\Controllers\Api\Admin\MobilePushController::class, 'store'])
                    ->middleware('throttle:10,1');
                Route::post('push/unregister', [\App\Http\Controllers\Api\Admin\MobilePushController::class, 'destroy'])
                    ->middleware('throttle:10,1');
                Route::get('orders', [\App\Http\Controllers\Api\Admin\MobileOrderController::class, 'index']);
                Route::get('orders/{order}', [\App\Http\Controllers\Api\Admin\MobileOrderController::class, 'show'])
                    ->whereNumber('order');
            });
    });
