<?php

namespace App\Services;

use App\Models\AdminPushDevice;
use App\Models\Order;
use Illuminate\Support\Facades\DB;

class AdminPushDelivery
{
    public function send(int $id): void
    {
        if (! config('admin_push.enabled')) return;
        // Atomic claim prevents simultaneous scheduler/manual runs sending the same row.
        $claimed = DB::table('admin_push_messages')->where('id', $id)->where('status', 'pending')
            ->where('available_at', '<=', now())->where('attempts', '<', 3)->update([
                'status' => 'processing', 'claimed_at' => now(), 'updated_at' => now(),
                'attempts' => DB::raw('attempts + 1'),
            ]);
        if (! $claimed) return;
        $message = DB::table('admin_push_messages')->find($id);
        if (! $message) return;
        $device = AdminPushDevice::with('accessToken.tokenable')->find($message->device_id);
        $order = Order::find($message->order_id);
        if (! $device || ! $device->authorized() || ! hash_equals($message->token_hash, $device->token_hash)
            || ! $order || in_array($order->status, ['cancelled', 'delivered'], true)
            || $order->created_at->lt(now()->subMinutes(15))) {
            $this->finish($id, 'skipped', 'no_longer_eligible');
            return;
        }
        try {
            $outcome = app(FirebaseAdminMessaging::class)->send($device->fcm_token, $order->id);
        } catch (\Throwable) {
            $this->finish($id, 'uncertain', 'unexpected_failure');
            return;
        }
        if ($outcome['status'] === 'invalid') {
            // Do not delete a newly rotated registration.
            AdminPushDevice::whereKey($device->id)->where('token_hash', $message->token_hash)->delete();
            return;
        }
        if ($outcome['status'] === 'retry' && $message->attempts < 3) {
            DB::table('admin_push_messages')->where('id', $id)->where('status', 'processing')->update([
                'status' => 'pending', 'result' => $outcome['result'], 'updated_at' => now(),
                'available_at' => now()->addSeconds(max($outcome['delay'], 60 * (2 ** ($message->attempts - 1))) + random_int(0, 15)),
            ]);
            return;
        }
        $this->finish($id, $outcome['status'] === 'retry' ? 'failed' : $outcome['status'], $outcome['result']);
    }

    private function finish(int $id, string $status, string $result): void
    {
        DB::table('admin_push_messages')->where('id', $id)->where('status', 'processing')
            ->update(['status' => $status, 'result' => $result, 'updated_at' => now()]);
    }
}
