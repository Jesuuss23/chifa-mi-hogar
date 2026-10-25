<?php

namespace App\Services;

use App\Models\StoreSetting;
use Illuminate\Validation\ValidationException;

class DeliveryService
{
    /**
     * Calcula la tarifa usando distancia geográfica aproximada.
     * Los importes se devuelven en céntimos.
     */
    public function calculate(
        StoreSetting $settings,
        float $customerLat,
        float $customerLng
    ): array {
        $kitchenLat = $settings->kitchen_latitude;
        $kitchenLng = $settings->kitchen_longitude;

        if ($kitchenLat === null || $kitchenLng === null) {
            throw ValidationException::withMessages([
                'delivery' => 'La ubicación de la cocina aún no está configurada.',
            ]);
        }

        $lat1 = (float) $kitchenLat;
        $lng1 = (float) $kitchenLng;

        if (
            $lat1 < -90 || $lat1 > 90 ||
            $lng1 < -180 || $lng1 > 180 ||
            $customerLat < -90 || $customerLat > 90 ||
            $customerLng < -180 || $customerLng > 180
        ) {
            throw ValidationException::withMessages([
                'delivery' => 'Las coordenadas proporcionadas no son válidas.',
            ]);
        }

        $earthRadiusKm = 6371.0088;

        $deltaLat = deg2rad($customerLat - $lat1);
        $deltaLng = deg2rad($customerLng - $lng1);

        $a = pow(sin($deltaLat / 2), 2)
            + cos(deg2rad($lat1))
            * cos(deg2rad($customerLat))
            * pow(sin($deltaLng / 2), 2);

        $a = min(1, max(0, $a));

        $distanceKm = 2 * $earthRadiusKm
            * asin(sqrt($a));

        $freeKm = max(0, (float) $settings->free_delivery_km);
        $costPerKm = max(
            0,
            (float) $settings->additional_cost_per_km
        );

        // Cada kilómetro adicional iniciado se cobra completo.
        $additionalKm = max(0, ceil(
            max(0, $distanceKm - $freeKm) - 0.0000001
        ));

        $costPerKmCents = (int) round($costPerKm * 100);
        $feeCents = (int) ($additionalKm * $costPerKmCents);

        return [
            'distance_km' => round($distanceKm, 3),
            'free_km' => $freeKm,
            'additional_km' => (int) $additionalKm,
            'fee_cents' => $feeCents,
            'fee' => number_format($feeCents / 100, 2, '.', ''),
        ];
    }
}