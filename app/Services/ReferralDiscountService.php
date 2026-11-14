<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReferralCampaign;
use App\Models\ReferralCoupon;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReferralDiscountService
{
    public function availableCoupons(?User $user)
    {
        return ReferralCoupon::where('user_id', $user?->role === 'customer' ? $user->id : 0)
            ->whereNull('used_at')->whereNull('reserved_order_id')->where('expires_at', '>', now())
            ->orderBy('expires_at')->get();
    }

    // Quote and confirmation share this calculation. Confirmation holds the store mutex
    // and row locks; quotations are advisory and never reserve a benefit.
    public function calculate(array $lines, mixed $couponId = null, bool $lock = false): array
    {
        $empty = ['discount_cents' => 0];
        $user = auth('web')->user();
        if (! $user || ! User::whereKey($user->id)->where('role', 'customer')->exists()) {
            if ($couponId !== null && $couponId !== '') {
                $this->invalid();
            }

            return $empty;
        }
        if ($couponId !== null && $couponId !== '') {
            if (! is_scalar($couponId) || ! preg_match('/^[1-9][0-9]{0,17}$/', (string) $couponId)) {
                $this->invalid();
            }
            $query = ReferralCoupon::whereKey($couponId)->where('user_id', $user->id);
            if ($lock) {
                $query->lockForUpdate();
            }
            $coupon = $query->first();
            if (! $coupon || $coupon->used_at || $coupon->reserved_order_id || $coupon->expires_at->lte(now())) {
                $this->invalid();
            }
            $terms = $coupon->only(['percent', 'minimum_cents', 'maximum_discount_cents', 'include_combos']);
            $source = ['coupon_id' => $coupon->id, 'invitation_id' => null];
        } else {
            $query = DB::table('referral_invitations')->where('customer_id', $user->id);
            if ($lock) {
                $query->lockForUpdate();
            }
            $invitation = $query->first();
            if (! $invitation || $invitation->used_at || $invitation->reserved_order_id) {
                return $empty;
            }
            $campaign = ReferralCampaign::accepting();
            if (! $campaign || $campaign->id !== (int) $invitation->campaign_id || ! $campaign->guest_percent) {
                return $empty;
            }
            if (! User::whereKey($invitation->inviter_id)->where('role', 'customer')->exists()) {
                return $empty;
            }
            $terms = $campaign->only(['inviter_percent', 'minimum_cents', 'maximum_discount_cents', 'coupon_days', 'include_combos']);
            $terms['percent'] = (int) $campaign->guest_percent;
            $source = ['invitation_id' => $invitation->id, 'coupon_id' => null];
        }
        $eligible = 0;
        foreach ($lines as $line) {
            if ($line['type'] !== 'combo' || $terms['include_combos']) {
                $eligible += $line['line_total_cents'];
            }
        }
        // A mandatory cap protects the margin; zero leaves benefits disabled.
        $discount = min($eligible, (int) $terms['maximum_discount_cents'], intdiv($eligible * (int) $terms['percent'], 100));
        if ($eligible < (int) $terms['minimum_cents'] || $discount < 1) {
            if ($source['coupon_id']) {
                $this->invalid('Tu carrito no cumple el mínimo o los productos permitidos de este cupón.');
            }

            return $empty;
        }

        return $source + ['terms' => $terms, 'eligible_cents' => $eligible, 'discount_cents' => $discount];
    }

    public function reserve(Order $order, array $discount): void
    {
        if (! $discount['discount_cents']) {
            return;
        }
        $table = $discount['coupon_id'] ? 'referral_coupons' : 'referral_invitations';
        $id = $discount['coupon_id'] ?: $discount['invitation_id'];
        $updated = DB::table($table)->where('id', $id)->whereNull('reserved_order_id')->whereNull('used_at')
            ->update(['reserved_order_id' => $order->id, 'updated_at' => now()]);
        if ($updated !== 1) {
            $this->invalid();
        }
        DB::table('referral_order_discounts')->insert([
            'order_id' => $order->id, 'invitation_id' => $discount['invitation_id'], 'coupon_id' => $discount['coupon_id'],
            'terms' => json_encode($discount['terms'], JSON_THROW_ON_ERROR),
            'eligible_cents' => $discount['eligible_cents'], 'discount_cents' => $discount['discount_cents'],
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // Called inside the existing order mutation transaction, after locking the order.
    // Never acquire the store mutex here: checkout locks store -> benefit, while
    // payment locks order -> benefit. Neither path waits on the other's first lock.
    public function orderChanged(Order $order): void
    {
        if (! $order->wasChanged(['payment_status', 'status'])) {
            return;
        }
        $record = DB::table('referral_order_discounts')->where('order_id', $order->id)->first();
        if (! $record || $record->released_at) {
            return;
        }
        $table = $record->coupon_id ? 'referral_coupons' : 'referral_invitations';
        $id = $record->coupon_id ?: $record->invitation_id;
        $benefit = DB::table($table)->where('id', $id)->lockForUpdate()->first();
        if (! $benefit || (int) $benefit->reserved_order_id !== $order->id) {
            return;
        }
        if ($order->status === 'cancelled' && $order->payment_status !== 'verified') {
            DB::table($table)->where('id', $id)->whereNull('used_at')
                ->update(['reserved_order_id' => null, 'updated_at' => now()]);
            DB::table('referral_order_discounts')->where('id', $record->id)
                ->update(['released_at' => now(), 'updated_at' => now()]);
        } elseif ($order->payment_status === 'verified' && $order->status !== 'cancelled') {
            DB::table($table)->where('id', $id)->whereNull('used_at')->update(['used_at' => now(), 'updated_at' => now()]);
            if ($record->invitation_id && User::whereKey($order->payment_verified_by)->where('role', 'admin')->exists()) {
                $this->award($order, (int) $order->payment_verified_by);
            }
        }
    }

    // Also supports admin approval of cash collected by a delivery employee.
    public function award(Order $order, int $adminId): void
    {
        abort_unless(User::whereKey($adminId)->where('role', 'admin')->exists(), 403);
        if ($order->payment_status !== 'verified' || $order->status === 'cancelled' || (float) $order->total <= 0) {
            return;
        }
        $record = DB::table('referral_order_discounts')->where('order_id', $order->id)->whereNull('released_at')->first();
        if (! $record || ! $record->invitation_id) {
            return;
        }
        $invitation = DB::table('referral_invitations')->where('id', $record->invitation_id)->lockForUpdate()->first();
        if (! $invitation || (int) $invitation->reserved_order_id !== $order->id) {
            return;
        }
        $terms = json_decode($record->terms, true, flags: JSON_THROW_ON_ERROR);
        if (! $terms['inviter_percent'] || ReferralCoupon::where('invitation_id', $invitation->id)->exists()) {
            return;
        }
        if (! User::whereKey($invitation->inviter_id)->where('role', 'customer')->exists()) {
            return;
        }
        ReferralCoupon::create([
            'invitation_id' => $invitation->id, 'user_id' => $invitation->inviter_id,
            'source_order_id' => $order->id, 'approved_by' => $adminId,
            'percent' => $terms['inviter_percent'], 'minimum_cents' => $terms['minimum_cents'],
            'maximum_discount_cents' => $terms['maximum_discount_cents'], 'include_combos' => $terms['include_combos'],
            'expires_at' => now()->addDays($terms['coupon_days']),
        ]);
    }

    private function invalid(string $message = 'El cupón no está disponible. Revisa tus cupones y vuelve a calcular.'): never
    {
        throw ValidationException::withMessages(['referral_coupon_id' => $message]);
    }
}
