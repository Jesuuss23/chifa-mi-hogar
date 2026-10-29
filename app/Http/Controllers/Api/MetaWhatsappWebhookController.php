<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use App\Models\WhatsappAdminNumber;
use App\Models\WhatsappCommandLog;
use App\Services\MetaWhatsappService;
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

    public function receive(
        Request $request,
        MetaWhatsappService $whatsapp
    ): Response {
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

        if (($payload['object'] ?? '') !== 'whatsapp_business_account') {
            return response('EVENT_RECEIVED', 200);
        }

        foreach (($payload['entry'] ?? []) as $entry) {
            foreach (($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }

                $value = $change['value'] ?? [];

                $receivedPhoneId = (string) (
                    $value['metadata']['phone_number_id'] ?? ''
                );

                $configuredPhoneId = (string) config(
                    'meta_whatsapp.phone_number_id',
                    ''
                );

                if (
                    $configuredPhoneId === ''
                    || $receivedPhoneId !== $configuredPhoneId
                ) {
                    continue;
                }

                foreach (($value['messages'] ?? []) as $message) {
                    try {
                        $this->processMessage($message, $whatsapp);
                    } catch (\Throwable $e) {
                        Log::error('Error procesando comando WhatsApp', [
                            'error' => $e->getMessage(),
                        ]);

                        return response('Processing failed', 500);
                    }
                }
            }
        }

        return response('EVENT_RECEIVED', 200);
    }

    private function processMessage(
        array $message,
        MetaWhatsappService $whatsapp
    ): void {
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
            trim((string) ($message['text']['body'] ?? '')),
            'UTF-8'
        );

        if ($messageId === '' || $sender === '') {
            return;
        }

        $commands = [
            'ABRIR CHIFA',
            'CERRAR CHIFA',
            'ESTADO CHIFA',
            'ACTIVAR VENTANA',
        ];

        // Validar que sea un administrador activo.
        $admin = WhatsappAdminNumber::query()
            ->where('phone', $sender)
            ->where('is_active', true)
            ->first();

        if (!$admin) {
            return;
        }

        // Registrar la fecha real del mensaje recibido por Meta.
        $timestamp = filter_var(
            $message['timestamp'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($timestamp !== false && $timestamp !== null) {
            $receivedAt = \Illuminate\Support\Carbon::createFromTimestamp(
                (int) $timestamp,
                'UTC'
            );

            // No permitir que mensajes antiguos o fechas futuras
            // alteren incorrectamente la ventana de atención.
            if (
                $receivedAt->lte(now()->addMinutes(2)) &&
                $receivedAt->gt($admin->last_inbound_at ?? now()->subYears(10))
            ) {
                // Compare against the stored value too: another webhook may
                // have advanced it since this administrator was loaded.
                WhatsappAdminNumber::query()
                    ->whereKey($admin->id)
                    ->where('is_active', true)
                    ->where(function ($query) use ($receivedAt) {
                        $query->whereNull('last_inbound_at')
                            ->orWhere('last_inbound_at', '<', $receivedAt);
                    })
                    ->update(['last_inbound_at' => $receivedAt]);
            }
        }

        // Un mensaje normal abre la ventana en Meta,
        // aunque no sea uno de nuestros comandos.
        if (!in_array($command, $commands, true)) {
            return;
        }

        // Solo los administradores con permiso pueden
        // ejecutar comandos que controlan la tienda.
        if (
            $command !== 'ACTIVAR VENTANA' &&
            !$admin->can_control_store
        ) {
            return;
        }

        // Guardar el comando y actualizar el negocio de forma atómica.
        // Si Meta reenvía el mismo ID, no ejecutaremos el comando otra vez.
        $result = DB::transaction(function () use (
            $messageId,
            $sender,
            $command
        ) {
            $log = WhatsappCommandLog::firstOrCreate(
                ['meta_message_id' => $messageId],
                [
                    'sender_phone' => $sender,
                    'command' => $command,
                    'result' => 'processing',
                ]
            );

            if (!$log->wasRecentlyCreated) {
                return null;
            }

            $settings = StoreSetting::current();

            $settings = StoreSetting::query()
                ->whereKey($settings->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($command === 'ABRIR CHIFA') {
                $settings->accepting_orders = true;
                $settings->save();
            }

            if ($command === 'CERRAR CHIFA') {
                $settings->accepting_orders = false;
                $settings->save();
            }

            $isOpen = (bool) $settings->accepting_orders;

            $log->update([
                'result' => $isOpen ? 'open' : 'closed',
            ]);

            return [
                'command' => $command,
                'is_open' => $isOpen,
            ];
        }, 3);

        if ($result === null) {
            return;
        }

        $reply = match ($command) {
            'ABRIR CHIFA' =>
                "🟢 CHIFA MI HOGAR\n\n"
                . "La recepción de pedidos está ABIERTA.\n"
                . "Los clientes ya pueden realizar sus pedidos.",

            'CERRAR CHIFA' =>
                "🔴 CHIFA MI HOGAR\n\n"
                . "La recepción de pedidos está CERRADA.\n"
                . "El catálogo seguirá visible, pero no se aceptarán nuevos pedidos.",
            'ACTIVAR VENTANA' =>
                "🟢 CHIFA MI HOGAR\n\n"
                . "Mensaje recibido correctamente.\n"
                . "El sistema registró tu última interacción "
                . "para las notificaciones de pedidos.\n\n"
                . "La ventana de atención de Meta dura 24 horas "
                . "desde tu último mensaje.",
            'ESTADO CHIFA' =>
                "📋 ESTADO DE CHIFA MI HOGAR\n\n"
                . (
                    $result['is_open']
                    ? "🟢 ABIERTO: recibiendo pedidos."
                    : "🔴 CERRADO: no se reciben pedidos."
                ),

            default => null,
        };

        if ($reply !== null) {
            $sent = $whatsapp->sendText($sender, $reply);

            if (!$sent) {
                Log::warning('Comando ejecutado sin respuesta WhatsApp', [
                    'command_log_message_id' => $messageId,
                ]);
            }
        }
    }
}