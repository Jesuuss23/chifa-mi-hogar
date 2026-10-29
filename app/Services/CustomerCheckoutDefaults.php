<?php

namespace App\Services;

use App\Models\User;

class CustomerCheckoutDefaults
{
    public function forUser(?User $user): array
    {
        if (! $user || $user->role !== 'customer') {
            return [];
        }

        $profile = $user->customerProfile;
        $defaults = [
            'customer_name' => $user->name,
            'customer_email' => $user->email,
            'customer_phone' => $profile?->phone,
        ];

        if ($profile && filled($profile->delivery_address)
            && $profile->delivery_latitude !== null && $profile->delivery_longitude !== null) {
            foreach (['delivery_address', 'delivery_reference', 'delivery_latitude', 'delivery_longitude'] as $field) {
                $defaults[$field] = $profile->$field;
            }
        }

        return $defaults;
    }
}
