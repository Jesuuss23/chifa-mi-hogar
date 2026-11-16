<?php

namespace App\Observers;

use App\Models\AdminPushDevice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AdminPushOrderObserver
{
    public function created(Order $order): void
    {
        if (! config('admin_push.enabled') || $order->status !== 'pending') return;
        // Nested transaction shares the checkout commit/rollback.
        // Row locks prevent logout deleting a device while its outbox row is inserted.
        DB::transaction(function () use ($order) {
            AdminPushDevice::with('accessToken.tokenable')->lockForUpdate()
                ->chunkById(100, function ($devices) use ($order) {
                    foreach ($devices as $device) {
                        if (! $device->authorized()) continue;
                        DB::table('admin_push_messages')->insertOrIgnore([
                            'order_id' => $order->id, 'device_id' => $device->id,
                            'token_hash' => $device->token_hash, 'status' => 'pending', 'attempts' => 0,
                            'available_at' => now(), 'created_at' => now(), 'updated_at' => now(),
                        ]);
                    }
                });
        });
    }
}
