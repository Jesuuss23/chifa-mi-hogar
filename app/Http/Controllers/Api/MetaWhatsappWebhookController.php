<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Models\WhatsappAdminNumber;
use App\Models\WhatsappCommandLog;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MetaWhatsappWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $mode = $request->query('hub_mode')
            ?? $request->query('hub.mode');

        $token = $request->query('hub_verify_token')
            ?? $request->query('hub.verify_token');

        $challenge = $request->query('hub_challenge')
            ?? $request->query('hub.challenge');

        $expected = config('meta_whatsapp.verify_token');

        if (
            $mode === 'subscribe'
            && is_string($expected)
            && $expected !== ''
            && is_string($token)
            && hash_equals($expected, $token)
        ) {
            return response((string) $challenge, 200);
        }

        return response('Forbidden', 403);
    }

    public function receive(Request $request): Response
    {
        $secret = config('meta_whatsapp.app_secret');

        if (!is_string($secret) || $secret === '') {
            return response('Webhook not configured', 503);
        }

        $signature = $request->header('X-Hub-Signature-256', '');

        $expected = 'sha256=' . hash_hmac(
            'sha256',
            $request->getContent(),
            $secret
        );

        if (
            !is_string($signature)
            || !hash_equals($expected, $signature)
        ) {
            return response('Invalid signature', 403);
        }

        $payload = $request->json()->all();

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                $value = $change['value'] ?? [];

                // Solo procesar mensajes del número técnico configurado.
                $phoneId = (string) (
                    $value['metadata']['phone_number_id'] ?? ''
                );

                $configuredId = (string) config(
                    'meta_whatsapp.phone_number_id'
                );

                if (
                    $configuredId === ''
                    || $phoneId !== $configuredId
                ) {
                    continue;
                }

                foreach (($value['messages'] ?? []) as $message) {
                    $this->processMessage($message);
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function processMessage(array $message): void
    {
        if (($message['type'] ?? '') !== 'text') {
            return;
        }

        $messageId = (string) ($message['id'] ?? '');
        $sender = preg_replace(
            '/\D+/',
            '',
            (string) ($message['from'] ?? '')
        );

        $command = mb_strtoupper(
            trim((string) ($message['text']['body'] ?? ''))
        );

        if ($messageId === '' || $sender === '') {
            return;
        }

        if (!in_array($command, [
            'ABRIR CHIFA',
            'CERRAR CHIFA',
            'ESTADO CHIFA',
        ], true)) {
            return;
        }

        $admin = WhatsappAdminNumber::query()
            ->where('phone', $sender)
            ->where('is_active', true)
            ->where('can_control_store', true)
            ->first();

        if (!$admin) {
            Log::warning('Comando WhatsApp no autorizado', [
                'sender' => substr($sender, -4),
            ]);

            return;
        }

        DB::transaction(function () use (
            $messageId,
            $sender,
            $command
        ) {
            // Impide ejecutar dos veces el mismo mensaje de Meta.
            $log = WhatsappCommandLog::firstOrCreate(
                ['meta_message_id' => $messageId],
                [
                    'sender_phone' => $sender,
                    'command' => $command,
                    'result' => 'processing',
                ]
            );

            if (!$log->wasRecentlyCreated) {
                return;
            }

            $settings = StoreSetting::query()
                ->lockForUpdate()
                ->find(1);

            if (!$settings) {
                $settings = StoreSetting::current();
            }

            if ($command === 'ABRIR CHIFA') {
                $settings->update([
                    'accepting_orders' => true,
                ]);
            }

            if ($command === 'CERRAR CHIFA') {
                $settings->update([
                    'accepting_orders' => false,
                ]);
            }

            $log->update([
                'result' => $settings->accepting_orders
                    ? 'open'
                    : 'closed',
            ]);
        });
    }
}