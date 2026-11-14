<?php

namespace App\Observers;

use App\Models\Order;
use App\Services\ReferralDiscountService;

class ReferralOrderObserver
{
    public function updated(Order $order): void
    {
        // Payment/cancellation controllers already lock the order in a transaction.
        app(ReferralDiscountService::class)->orderChanged($order);
    }
}
