<?php

namespace App\Services;

use App\Models\Order;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DeliveryRouteService
{
    public function getRoute(Order $order): ?array
    {
        if (
            $order->status !== 'on_the_way' ||
            $order->delivery_type !== 'delivery' ||
            $order->driver_tracking_started_at === null ||
            $order->driver_tracking_stopped_at !== null ||
            $order->driver_location_updated_at === null ||
            $order->driver_location_updated_at->lt(now()->subSeconds(60))
        ) {
            return null;
        }

        $coordinates = [
            $order->driver_latitude,
            $order->driver_longitude,
            $order->delivery_latitude,
            $order->delivery_longitude,
        ];

        if (in_array(null, $coordinates, true)) {
            return null;
        }

        [$fromLat, $fromLng, $toLat, $toLng] =
            array_map('floatval', $coordinates);

        if (
            abs($fromLat) > 90 ||
            abs($toLat) > 90 ||
            abs($fromLng) > 180 ||
            abs($toLng) > 180
        ) {
            return null;
        }

        $apiKey = config('services.geoapify.key');

        if (!$apiKey) {
            return null;
        }

        // Una ruta por pedido y por sesión de seguimiento.
        $cacheKey = 'geoapify_route_' . hash(
            'sha256',
            $order->id . '|' .
            $order->driver_tracking_started_at->timestamp
        );

        return Cache::remember(
            $cacheKey,
            now()->addHours(24),
            function () use (
                $apiKey,
                $fromLat,
                $fromLng,
                $toLat,
                $toLng
            ) {
                try {
                    $waypoints = sprintf(
                        '%.7F,%.7F|%.7F,%.7F',
                        $fromLat,
                        $fromLng,
                        $toLat,
                        $toLng
                    );

                    $response = Http::connectTimeout(3)
                        ->timeout(10)
                        ->get(
                            'https://api.geoapify.com/v1/routing',
                            [
                                'waypoints' => $waypoints,
                                'mode' => 'motorcycle',
                                'format' => 'geojson',
                                'apiKey' => $apiKey,
                            ]
                        );

                    if (!$response->successful()) {
                        Log::warning(
                            'Geoapify no devolvió una ruta',
                            ['status' => $response->status()]
                        );

                        return null;
                    }

                    $feature = $response->json('features.0');
                    $geometry = data_get($feature, 'geometry');

                    if (!is_array($geometry)) {
                        return null;
                    }

                    // Geoapify devuelve MultiLineString.
                    // Con dos puntos, normalmente existe una sola línea.
                    if (($geometry['type'] ?? null) === 'MultiLineString') {
                        $lines = $geometry['coordinates'] ?? [];

                        if (count($lines) !== 1) {
                            return null;
                        }

                        $geometry = [
                            'type' => 'LineString',
                            'coordinates' => $lines[0],
                        ];
                    }

                    if (
                        ($geometry['type'] ?? null) !== 'LineString' ||
                        count($geometry['coordinates'] ?? []) < 2
                    ) {
                        return null;
                    }

                    return [
                        'geometry' => $geometry,
                        'distance_km' => round(
                            (float) data_get(
                                $feature,
                                'properties.distance',
                                0
                            ) / 1000,
                            2
                        ),
                    ];

                } catch (\Throwable $exception) {
                    Log::warning(
                        'Error consultando Geoapify',
                        ['error_type' => get_class($exception)]
                    );

                    return null;
                }
            }
        );
    }
}