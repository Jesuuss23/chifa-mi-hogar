<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WhatsappAdminNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderWhatsappNotificationService
{
    public const MAX_ATTEMPTS = 3;

    public function __construct(
        private readonly MetaWhatsappService $whatsapp
    ) {}

    public function send(Order $order): void
    {
        // No enviar avisos de pedidos terminados ni avisos antiguos como nuevos.
        $order->refresh();
        if (
            in_array($order->status, ['delivered', 'cancelled'], true)
            || $order->created_at->lt(now()->subDay())
        ) {
            return;
        }

        $order->loadMissing(['items.extras', 'items.comboComponents']);
        $admins = WhatsappAdminNumber::query()
            ->where('is_active', true)
            ->where('receive_orders', true)
            ->get();
        $message = $this->buildMessage($order);

        foreach ($admins as $admin) {
            DB::table('order_whatsapp_notifications')->insertOrIgnore([
                'order_id' => $order->id,
                'whatsapp_admin_number_id' => $admin->id,
                'status' => 'pending',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            // Sin ventana no se consume un intento ni se llama a Meta.
            if (
                $admin->last_inbound_at === null
                || $admin->last_inbound_at->lte(now()->subHours(23))
                || $admin->last_inbound_at->gt(now())
            ) {
                continue;
            }

            $claim = (string) Str::uuid();
            $notification = DB::transaction(function () use ($order, $admin, $claim) {
                $row = DB::table('order_whatsapp_notifications')
                    ->where('order_id', $order->id)
                    ->where('whatsapp_admin_number_id', $admin->id)
                    ->lockForUpdate()
                    ->first();

                if (
                    !in_array($row->status, ['pending', 'failed'], true)
                    || $row->attempts >= self::MAX_ATTEMPTS
                    || ($row->next_attempt_at !== null && now()->lt($row->next_attempt_at))
                ) {
                    return null;
                }

                DB::table('order_whatsapp_notifications')->where('id', $row->id)->update([
                    'status' => 'processing',
                    'attempts' => $row->attempts + 1,
                    'attempt_token' => $claim,
                    'next_attempt_at' => null,
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

                return ['id' => $row->id, 'attempts' => $row->attempts + 1];
            }, 3);

            if ($notification === null) {
                continue;
            }

            try {
                $result = $this->whatsapp->sendTextResult($admin->phone, $message);
            } catch (\Throwable $exception) {
                $result = 'uncertain';
                Log::error('Resultado de notificación WhatsApp desconocido', [
                    'notification_id' => $notification['id'],
                    'exception' => get_class($exception),
                ]);
            }

            if (!in_array($result, ['sent', 'failed', 'uncertain'], true)) {
                $result = 'uncertain';
            }

            // Solo el propietario del intento puede finalizarlo.
            DB::table('order_whatsapp_notifications')
                ->where('id', $notification['id'])
                ->where('status', 'processing')
                ->where('attempt_token', $claim)
                ->update([
                    'status' => $result,
                    'sent_at' => $result === 'sent' ? now() : null,
                    'attempt_token' => null,
                    'next_attempt_at' => $result === 'failed'
                        && $notification['attempts'] < self::MAX_ATTEMPTS
                            ? now()->addSeconds($notification['attempts'] === 1 ? 60 : 300)
                            : null,
                    'last_error' => match ($result) {
                        'sent' => null,
                        'failed' => 'Meta rechazó el envío o faltan datos de configuración.',
                        default => 'Resultado desconocido. Revisar antes de reenviar.',
                    },
                    'updated_at' => now(),
                ]);
        }
    }

    private function buildMessage(Order $order): string
    {
        $phone = preg_replace(
            '/\D+/',
            '',
            (string) $order->customer_phone
        );

        if (strlen($phone) === 9) {
            $phone = '51' . $phone;
        }

        $lines = [
            "🔔 *NUEVO PEDIDO WEB*",
            "🍜 *CHIFA MI HOGAR*",
            "",
            "🧾 *Pedido:* {$order->code}",
            "👤 *Cliente:* {$order->customer_name}",
            "📱 *Teléfono:* {$order->customer_phone}",
            "",
            "💬 *Hablar con el cliente:*",
            "https://wa.me/{$phone}",
            "",
            "📦 *TIPO DE ENTREGA:*",
            $order->delivery_type === 'delivery'
                ? 'Delivery'
                : 'Recojo',
        ];

        if ($order->delivery_type === 'delivery') {
            $lines[] = "";
            $lines[] = "📍 *Dirección:* {$order->delivery_address}";

            if ($order->delivery_reference) {
                $lines[] =
                    "🏠 *Referencia:* {$order->delivery_reference}";
            }

            if (
                $order->delivery_latitude !== null &&
                $order->delivery_longitude !== null
            ) {
                $lat = $order->delivery_latitude;
                $lng = $order->delivery_longitude;

                $lines[] = "";
                $lines[] = "🗺️ *Abrir en Google Maps:*";
                $lines[] =
                    "https://www.google.com/maps/search/?api=1&query={$lat}%2C{$lng}";
            }
        }

        $lines[] = "";
        $lines[] = "🍽️ *PRODUCTOS:*";

        foreach ($order->items as $item) {
            $lines[] =
                "• {$item->quantity} x {$item->item_name}"
                . " — S/ {$item->line_total}";

            foreach ($item->extras as $extra) {
                $lines[] =
                    "   + {$extra->extra_name}"
                    . " x {$extra->quantity}";
            }

            foreach ($item->comboComponents as $component) {
                $lines[] =
                    "   - {$component->component_name}"
                    . " x {$component->selected_quantity}";
            }

            if ($item->notes) {
                $lines[] = "   Nota: {$item->notes}";
            }
        }

        $lines[] = "";
        $lines[] = "💰 *RESUMEN:*";
        $lines[] = "Subtotal: S/ {$order->subtotal}";
        $lines[] = "Delivery: S/ {$order->delivery_fee}";
        $lines[] = "*TOTAL: S/ {$order->total}*";
        $lines[] = "";
        $lines[] =
            "💳 *Pago:* " . strtoupper($order->payment_method);
        $lines[] =
            "📋 *Estado del pago:* {$order->payment_status}";

        if ($order->customer_notes) {
            $lines[] = "";
            $lines[] =
                "📝 *Observaciones:* {$order->customer_notes}";
        }

        $lines[] = "";
        $lines[] = "⚠️ Pedido registrado en la web.";
        $lines[] = "Contactar al cliente si aún no confirmó por WhatsApp.";

        return implode("\n", $lines);
    }
}
