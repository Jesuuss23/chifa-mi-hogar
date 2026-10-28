<?php

namespace App\Console\Commands;

use App\Jobs\SendOrderWhatsappNotification;
use App\Services\OrderWhatsappNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RetryOrderWhatsappNotifications extends Command
{
    protected $signature = 'whatsapp:retry-orders {--review : Mostrar avisos que requieren revisión sin reenviarlos}';
    protected $description = 'Recuperar avisos pendientes y marcar envíos interrumpidos para revisión';

    public function handle(): int
    {
        if ($this->option('review')) {
            $rows = DB::table('order_whatsapp_notifications')
                ->whereIn('status', ['uncertain', 'failed', 'processing'])
                ->orderByDesc('id')->limit(100)
                ->get(['id', 'order_id', 'status', 'attempts', 'updated_at'])
                ->map(fn ($row) => (array) $row)->all();
            $this->table(['ID', 'Pedido', 'Estado', 'Intentos', 'Actualizado'], $rows);

            return self::SUCCESS;
        }

        $uncertain = DB::table('order_whatsapp_notifications')
            ->where('status', 'processing')
            ->where('updated_at', '<=', now()->subMinutes(5))
            ->update([
                'status' => 'uncertain',
                'attempt_token' => null,
                'next_attempt_at' => null,
                'last_error' => 'Proceso interrumpido. Revisar recepción antes de reenviar.',
                'updated_at' => now(),
            ]);

        $orderIds = DB::table('order_whatsapp_notifications as notifications')
            ->join('orders', 'orders.id', '=', 'notifications.order_id')
            ->join('whatsapp_admin_numbers as admins', 'admins.id', '=', 'notifications.whatsapp_admin_number_id')
            ->whereIn('notifications.status', ['pending', 'failed'])
            ->where('notifications.attempts', '<', OrderWhatsappNotificationService::MAX_ATTEMPTS)
            ->where(function ($query) {
                $query->whereNull('notifications.next_attempt_at')
                    ->orWhere('notifications.next_attempt_at', '<=', now());
            })
            ->where('admins.is_active', true)
            ->where('admins.receive_orders', true)
            ->where('admins.last_inbound_at', '>', now()->subHours(23))
            ->where('admins.last_inbound_at', '<=', now())
            ->whereNotIn('orders.status', ['delivered', 'cancelled'])
            ->where('orders.created_at', '>=', now()->subDay())
            ->select('notifications.order_id')
            ->distinct()
            ->orderBy('notifications.order_id')
            ->limit(100)
            ->pluck('order_id');

        foreach ($orderIds as $orderId) {
            SendOrderWhatsappNotification::dispatch((int) $orderId);
        }

        $this->info("Pedidos seleccionados: {$orderIds->count()}; envíos interrumpidos para revisión: {$uncertain}.");

        return self::SUCCESS;
    }
}
