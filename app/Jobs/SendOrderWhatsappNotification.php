<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderWhatsappNotificationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SendOrderWhatsappNotification implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly int $orderId
    ) {
        $this->onQueue('whatsapp-orders');
        $this->afterCommit();
    }

    public function handle(
        OrderWhatsappNotificationService $notifications
    ): void {
        $order = Order::find($this->orderId);

        if (!$order) {
            Log::warning(
                'Pedido no encontrado para notificación WhatsApp',
                ['order_id' => $this->orderId]
            );

            return;
        }

        $notifications->send($order);
    }
}
