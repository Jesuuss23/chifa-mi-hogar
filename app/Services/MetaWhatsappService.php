<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsappService
{
    public function sendText(string $to, string $message): bool
    {
        $token = config('meta_whatsapp.access_token');
        $phoneId = config('meta_whatsapp.phone_number_id');
        $version = config('meta_whatsapp.api_version', 'v25.0');

        if (empty($token) || empty($phoneId)) {
            Log::error('Meta WhatsApp: faltan credenciales de envío.');
            return false;
        }

        $to = preg_replace('/\D+/', '', $to);

        if (!$to) {
            return false;
        }

        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->timeout(10)
                ->post(
                    "https://graph.facebook.com/{$version}/{$phoneId}/messages",
                    [
                        'messaging_product' => 'whatsapp',
                        'recipient_type' => 'individual',
                        'to' => $to,
                        'type' => 'text',
                        'text' => [
                            'preview_url' => false,
                            'body' => $message,
                        ],
                    ]
                );

            if ($response->failed()) {
                Log::error('Meta WhatsApp: error de envío', [
                    'status' => $response->status(),
                    'error' => $response->json('error.code'),
                    'type' => $response->json('error.type'),
                ]);

                return false;
            }

            return !empty($response->json('messages.0.id'));

        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp: fallo de conexión', [
                'message' => $e->getMessage(),
            ]);

            return false;
        }
    }
}