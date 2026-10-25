<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrderTrackingController extends Controller
{
    private function findOrder(string $token): Order
    {
        abort_unless(
            preg_match(
                '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
                $token
            ),
            404
        );

        return Order::query()
            ->where('tracking_token', $token)
            ->firstOrFail();
    }

    public function show(string $token): View
    {
        $order = $this->findOrder($token);

        return view('storefront.order-tracking', [
            'order' => $order,
            'trackingToken' => $token,
        ]);
    }

    public function status(Request $request, string $token): JsonResponse
    {
        $order = $this->findOrder($token);

        return response()->json([
            'status' => $order->status,
            'payment_status' => $order->payment_status,
            'updated_at' => $order->updated_at?->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store');
    }

    public function route(
        string $token,
        \App\Services\DeliveryRouteService $routing
    ): JsonResponse {
        $order = $this->findOrder($token);

        $active = (
            $order->status === 'on_the_way' &&
            $order->delivery_type === 'delivery' &&
            $order->driver_tracking_started_at !== null &&
            $order->driver_tracking_stopped_at === null
        );

        $fresh = (
            $active &&
            $order->driver_location_updated_at !== null &&
            $order->driver_location_updated_at->gte(now()->subSeconds(60)) &&
            $order->driver_latitude !== null &&
            $order->driver_longitude !== null
        );

        if (!$fresh) {
            return response()->json([
                'available' => false,
                'gps_active' => $active,
                'gps_fresh' => false,
                'route' => null,
                'driver' => null,
                'destination' => null,
            ])->header('Cache-Control', 'private, no-store');
        }

        $route = $routing->getRoute($order);

        return response()->json([
            'available' => $route !== null,
            'gps_active' => true,
            'gps_fresh' => true,
            'driver' => [
                'latitude' => (float) $order->driver_latitude,
                'longitude' => (float) $order->driver_longitude,
            ],
            'destination' => [
                'latitude' => (float) $order->delivery_latitude,
                'longitude' => (float) $order->delivery_longitude,
            ],
            'route' => $route,
            'location_updated_at' =>
                $order->driver_location_updated_at->toIso8601String(),
        ])->header('Cache-Control', 'private, no-store');
    }
}
