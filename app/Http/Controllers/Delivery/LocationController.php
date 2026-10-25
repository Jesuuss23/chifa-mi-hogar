<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LocationController extends Controller
{
    private function authorizeOrder(Request $request, Order $order): void
    {
        abort_unless(
            $request->user()?->isDelivery()
            && (int) $order->delivery_user_id === (int) $request->user()->id,
            403,
            'No tienes permiso para actualizar esta entrega.'
        );

        abort_unless(
            $order->delivery_type === 'delivery',
            422,
            'Este pedido no requiere reparto.'
        );
    }

    public function start(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        $result = DB::transaction(function () use ($order) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status !== 'on_the_way') {
                return false;
            }
            if (
                $locked->driver_tracking_started_at !== null &&
                $locked->driver_tracking_stopped_at === null
            ) {
                return true;
            }
            $locked->driver_tracking_started_at = now();
            $locked->driver_tracking_stopped_at = null;

            // No reutilizar una ubicación de una sesión anterior.
            $locked->driver_latitude = null;
            $locked->driver_longitude = null;
            $locked->driver_location_updated_at = null;

            $locked->save();

            return true;
        }, 3);

        if (!$result) {
            return response()->json([
                'message' => 'El pedido debe estar en camino.',
            ], 422);
        }

        return response()->json([
            'message' => 'Seguimiento GPS iniciado.',
        ])->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:10000'],
        ]);

        if ((float) $data['accuracy'] > 100) {
            return response()->json([
                'message' => 'La precisión GPS es insuficiente.',
            ], 422);
        }

        $result = DB::transaction(function () use ($order, $data) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locked->status !== 'on_the_way'
                || $locked->driver_tracking_started_at === null
                || $locked->driver_tracking_stopped_at !== null
            ) {
                return 'El seguimiento no está activo.';
            }

            // Limitar escrituras aunque lleguen peticiones muy seguidas.
            if (
                $locked->driver_location_updated_at !== null
                && $locked->driver_location_updated_at->gt(now()->subSeconds(4))
            ) {
                return 'wait';
            }

            $locked->driver_latitude = $data['latitude'];
            $locked->driver_longitude = $data['longitude'];
            $locked->driver_location_updated_at = now();
            $locked->save();

            return 'saved';
        }, 3);

        if ($result === 'wait') {
            return response()->json([
                'message' => 'Esperando próxima actualización.',
            ])->header('Cache-Control', 'no-store');
        }

        if ($result !== 'saved') {
            return response()->json([
                'message' => $result,
            ], 422);
        }

        return response()->json([
            'message' => 'Ubicación actualizada.',
        ])->header('Cache-Control', 'no-store');
    }

    public function stop(Request $request, Order $order): JsonResponse
    {
        $this->authorizeOrder($request, $order);

        DB::transaction(function () use ($order) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->driver_tracking_stopped_at = now();
            $locked->driver_latitude = null;
            $locked->driver_longitude = null;
            $locked->driver_location_updated_at = null;

            $locked->save();
        }, 3);

        return response()->json([
            'message' => 'Seguimiento GPS detenido.',
        ])->header('Cache-Control', 'no-store');
    }
}
