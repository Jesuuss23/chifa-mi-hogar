<?php

namespace App\Services;

use App\Models\Order;
use App\Models\ReferralCampaign;
use App\Models\StoreSetting;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReferralService
{
    // Same mutex as OrderService: linking and placing a first order cannot race.
    public function lockProgram(): void
    {
        $settings = StoreSetting::current();
        StoreSetting::whereKey($settings->id)->lockForUpdate()->firstOrFail();
    }

    public function codeFor(User $user): string
    {
        return DB::transaction(function () use ($user) {
            $this->lockProgram();
            abort_unless(User::whereKey($user->id)->where('role', 'customer')->exists(), 403);
            $existing = DB::table('referral_codes')->where('user_id', $user->id)->value('code');
            if ($existing) {
                return $existing;
            }
            do {
                $code = strtoupper(bin2hex(random_bytes(12)));
            } while (DB::table('referral_codes')->where('code', $code)->exists());
            DB::table('referral_codes')->insert([
                'user_id' => $user->id, 'code' => $code, 'created_at' => now(), 'updated_at' => now(),
            ]);
            return $code;
        }, 3);
    }

    public function apply(User $customer, string $code): void
    {
        DB::transaction(function () use ($customer, $code) {
            $this->lockProgram();
            abort_unless(User::whereKey($customer->id)->where('role', 'customer')->exists(), 403);
            $owner = DB::table('referral_codes')->where('code', strtoupper(trim($code)))->first();
            $existing = DB::table('referral_invitations')->where('customer_id', $customer->id)->first();
            // A retry after a lost response returns the persisted result, even after a pause.
            if ($existing) {
                if ($owner && (int) $existing->inviter_id === (int) $owner->user_id) {
                    return;
                }
                $this->invalid('Tu cuenta ya tiene un código de invitación vinculado.');
            }
            $campaign = ReferralCampaign::accepting();
            if (! $campaign) {
                $this->invalid('La campaña no está disponible.');
            }
            if (! $owner || (int) $owner->user_id === (int) $customer->id
                || ! User::whereKey($owner->user_id)->where('role', 'customer')->exists()) {
                $this->invalid('No puedes utilizar este código.');
            }
            // Reserve attribution before ANY order, including pending/cancelled orders.
            if (Order::where('user_id', $customer->id)->exists()) {
                $this->invalid('El código debe vincularse antes de tu primer pedido.');
            }
            $cursor = (int) $owner->user_id;
            $visited = [];
            while ($cursor) {
                if ($cursor === (int) $customer->id || isset($visited[$cursor]) || count($visited) >= 100) {
                    $this->invalid('No puedes utilizar este código.');
                }
                $visited[$cursor] = true;
                $cursor = (int) DB::table('referral_invitations')->where('customer_id', $cursor)->value('inviter_id');
            }
            DB::table('referral_invitations')->insert([
                'campaign_id' => $campaign->id, 'inviter_id' => $owner->user_id,
                'customer_id' => $customer->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }, 3);
    }

    private function invalid(string $message): never
    {
        throw ValidationException::withMessages(['referral_code' => $message]);
    }
}
