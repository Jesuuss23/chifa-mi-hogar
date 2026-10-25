<?php

namespace App\Services;

use App\Models\Order;
use App\Models\WhatsappAdminNumber;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;
class OrderWhatsappNotificationService
{
    public function __construct(
        private readonly MetaWhatsappService $whatsapp
    ) {}

    public function send(Order $order): void
    {
        $order->loadMissing([
            'items.extras',
            'items.comboComponents',
        ]);

        $admins = WhatsappAdminNumber::query()
            ->where('is_active', true)
            ->where('receive_orders', true)
            ->get();

        $message = $this->buildMessage($order);

        foreach ($admins as $admin) {

            $notification = DB::table('order_whatsapp_notifications')
                ->where('order_id', $order->id)
                ->where('whatsapp_admin_number_id', $admin->id)
                ->first();

            if (!$notification) {
                DB::table('order_whatsapp_notifications')
                    ->insertOrIgnore([
                        'order_id' => $order->id,
                        'whatsapp_admin_number_id' => $admin->id,
                        'status' => 'pending',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
            }

            // Reclamar el envío de forma atómica.
            // Un registro sent o processing no se envía otra vez.
            $claimed = DB::table('order_whatsapp_notifications')
                ->where('order_id', $order->id)
                ->where('whatsapp_admin_number_id', $admin->id)
                ->whereIn('status', ['pending', 'failed'])
                ->update([
                    'status' => 'processing',
                    'last_error' => null,
                    'updated_at' => now(),
                ]);

            if ($claimed !== 1) {
                continue;
            }

            // Margen de seguridad antes de las 24 horas.
            $windowOpen = $admin->last_inbound_at !== null
                && $admin->last_inbound_at->gt(
                    now()->subHours(23)
                );

            if (!$windowOpen) {
                DB::table('order_whatsapp_notifications')
                    ->where('order_id', $order->id)
                    ->where('whatsapp_admin_number_id', $admin->id)
                    ->update([
                        'status' => 'pending',
                        'last_error' => 'Ventana de WhatsApp no vigente.',
                        'updated_at' => now(),
                    ]);

                continue;
            }

            try {
                $sent = $this->whatsapp->sendText(
                    $admin->phone,
                    $message
                );

                DB::table('order_whatsapp_notifications')
                    ->where('order_id', $order->id)
                    ->where('whatsapp_admin_number_id', $admin->id)
                    ->update([
                        'status' => $sent ? 'sent' : 'failed',
                        'sent_at' => $sent ? now() : null,
                        'last_error' => $sent
                            ? null
                            : 'Meta no confirmó la aceptación del mensaje.',
                        'updated_at' => now(),
                    ]);

            } catch (\Throwable $e) {

                DB::table('order_whatsapp_notifications')
                    ->where('order_id', $order->id)
                    ->where('whatsapp_admin_number_id', $admin->id)
                    ->update([
                        'status' => 'failed',
                        'last_error' => 'Error durante el envío.',
                        'updated_at' => now(),
                    ]);

                Log::error(
                    'Error notificando pedido por WhatsApp',
                    [
                        'order_id' => $order->id,
                        'admin_id' => $admin->id,
                        'exception' => get_class($e),
                    ]
                );
            }
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
