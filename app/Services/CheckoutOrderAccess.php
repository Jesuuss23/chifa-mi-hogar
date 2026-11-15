<?php

namespace App\Services;

use App\Models\Order;

final class CheckoutOrderAccess
{
    // Call only AFTER validating the session checkout token/order reference.
    // Guest orders remain session-bound; logging in does not claim their ownership.
    public static function authorize(Order $order): void
    {
        abort_unless(
            $order->user_id === null || (int) $order->user_id === (int) auth('web')->id(),
            404
        );
    }
}
