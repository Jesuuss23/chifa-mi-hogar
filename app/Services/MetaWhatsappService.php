<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MetaWhatsappService
{
    public function sendText(string $to, string $message): bool
    {
        return $this->sendTextResult($to, $message) === 'sent';
    }

    // Mantener sendText() para las respuestas del webhook.
    public function sendTextResult(string $to, string $message): string
    {
        $token = config('meta_whatsapp.access_token');
        $phoneId = config('meta_whatsapp.phone_number_id');
        $version = config('meta_whatsapp.api_version', 'v25.0');

        if (empty($token) || empty($phoneId)) {
            Log::error('Meta WhatsApp: faltan credenciales de envío.');
            return 'failed';
        }

        $to = preg_replace('/\D+/', '', $to);

        if (!$to) {
            return 'failed';
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

                // Un error de servidor o timeout puede llegar después de aceptar el envío.
                return $response->serverError() || $response->status() === 408
                    ? 'uncertain'
                    : 'failed';
            }

            return !empty($response->json('messages.0.id')) ? 'sent' : 'uncertain';

        } catch (\Throwable $e) {
            Log::error('Meta WhatsApp: fallo de conexión', [
                'message' => $e->getMessage(),
            ]);

            return 'uncertain';
        }
    }
}