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