<?php

namespace App\Services;

use Illuminate\Http\Request;

class CheckoutAuthDraft
{
    public const KEY = 'checkout_auth_draft';

    public function save(Request $request, array $data): void
    {
        foreach (['delivery_address', 'delivery_reference', 'delivery_latitude', 'delivery_longitude'] as $field) {
            $data[$field] = $data[$field] ?? null;
        }
        $lat = $data['delivery_latitude'];
        $lng = $data['delivery_longitude'];
        if (! filled($data['delivery_address']) || ! is_numeric($lat) || ! is_numeric($lng)
            || ! is_finite((float) $lat) || ! is_finite((float) $lng)
            || abs((float) $lat) > 90 || abs((float) $lng) > 180) {
            $data['delivery_latitude'] = null;
            $data['delivery_longitude'] = null;
        }
        $request->session()->put(self::KEY, [
            'created_at' => now()->timestamp,
            'checkout_token' => $request->session()->get('checkout_token'),
            'user_id' => null, 'data' => $data,
        ]);
        $request->session()->forget(['checkout_quote', '_old_input']);
    }

    public function restore(Request $request): array
    {
        $draft = $request->session()->get(self::KEY);
        if (! is_array($draft) || ! is_int($draft['created_at'] ?? null)
            || now()->timestamp < $draft['created_at'] || now()->timestamp - $draft['created_at'] > 1800
            || ! is_string($draft['checkout_token'] ?? null)
            || $draft['checkout_token'] !== $request->session()->get('checkout_token')
            || ! is_array($draft['data'] ?? null)
            || (($draft['user_id'] ?? null) !== null && $draft['user_id'] !== $request->user()?->id)) {
            $request->session()->forget(self::KEY);
            return [];
        }
        if ($request->user()) {
            if ($request->user()->role !== 'customer') {
                $request->session()->forget(self::KEY);
                return [];
            }
            $draft['user_id'] = $request->user()->id;
            $request->session()->put(self::KEY, $draft);
        }
        return $draft['data'];
    }

    public function defaults(array $profile, array $draft): array
    {
        foreach (['customer_name', 'customer_phone', 'customer_email'] as $field) {
            if (filled($draft[$field] ?? null)) {
                $profile[$field] = $draft[$field];
            }
        }
        if ($this->hasDelivery($draft)) {
            foreach (['delivery_address', 'delivery_reference', 'delivery_latitude', 'delivery_longitude'] as $field) {
                $profile[$field] = $draft[$field] ?? null;
            }
        }
        return $profile;
    }

    public function hasDelivery(array $draft): bool
    {
        return filled($draft['delivery_address'] ?? null) || filled($draft['delivery_reference'] ?? null);
    }
}
