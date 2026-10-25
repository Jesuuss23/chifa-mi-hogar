<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class OrderController extends Controller
{
    private const STATUSES = [
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'preparing' => 'Preparando',
        'on_the_way' => 'En camino',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',
    ];

    private const TRANSITIONS = [
        'pending' => ['confirmed', 'cancelled'],
        'confirmed' => ['preparing', 'cancelled'],
        'preparing' => ['on_the_way', 'cancelled'],
        'on_the_way' => ['delivered'],
        'delivered' => [],
        'cancelled' => [],
    ];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => [
                'nullable',
                Rule::in(array_keys(self::STATUSES)),
            ],
            'search' => ['nullable', 'string', 'max:150'],
        ]);

        $orders = Order::query()
            ->withCount('items')
            ->when(
                $filters['status'] ?? null,
                fn ($query, $status) => $query->where('status', $status)
            )
            ->when(
                $filters['search'] ?? null,
                function ($query, $search) {
                    $query->where(function ($query) use ($search) {
                        $query->where('code', 'ilike', "%{$search}%")
                            ->orWhere('customer_name', 'ilike', "%{$search}%")
                            ->orWhere('customer_phone', 'ilike', "%{$search}%");
                    });
                }
            )
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $counts = Order::query()
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('admin.orders.index', [
            'orders' => $orders,
            'counts' => $counts,
            'statuses' => self::STATUSES,
        ]);
    }

    public function show(Order $order): View
    {
        $order->load([
            'items.extras',
            'statusHistories.changedBy',
            'paymentReceipt',
            'paymentVerifier',
        ]);

        return view('admin.orders.show', [
            'order' => $order,
            'statuses' => self::STATUSES,
            'nextStatuses' => self::TRANSITIONS[$order->status] ?? [],
            'drivers' => \App\Models\User::query()
                ->where('role', 'delivery')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function verifyPayment(
        Request $request,
        Order $order
    ): RedirectResponse {
        $data = $request->validate([
            'decision' => [
                'required',
                Rule::in(['verified', 'rejected']),
            ],
        ]);

        $error = DB::transaction(function () use ($order, $data) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (!in_array($locked->payment_method, ['yape', 'plin'], true)) {
                return 'Los pagos en efectivo se registran desde la sección de cobro contra entrega.';
            }

            if (in_array($locked->status, ['cancelled', 'delivered'], true)) {
                return 'Este pedido ya no permite modificar el pago.';
            }

            if ($locked->payment_status === 'verified') {
                return 'Este pago ya fue verificado.';
            }

            if (
                $data['decision'] === 'rejected'
                && $locked->status !== 'pending'
            ) {
                return 'Solo puedes rechazar pagos de pedidos pendientes.';
            }

            $locked->payment_status = $data['decision'];
            $locked->payment_verified_at =
                $data['decision'] === 'verified' ? now() : null;
            $locked->payment_verified_by =
                $data['decision'] === 'verified' ? auth()->id() : null;

            $locked->save();

            return null;
        }, 3);

        if ($error !== null) {
            return back()->withErrors(['payment' => $error]);
        }

        return back()->with('success', 'Estado del pago actualizado.');
    }

    public function collectCashPayment(
        Request $request,
        Order $order
    ): RedirectResponse {
        $data = $request->validate([
            'confirm_collection' => [
                'required',
                'accepted',
            ],
        ]);

        $error = DB::transaction(function () use ($order) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->payment_method !== 'cash') {
                return 'Este pedido no utiliza pago en efectivo.';
            }

            if (in_array($locked->status, ['cancelled', 'delivered'], true)) {
                return 'No se puede registrar el cobro de este pedido.';
            }

            if ($locked->payment_status === 'verified') {
                return 'Este pedido ya figura como pagado.';
            }

            if (!in_array($locked->status, ['confirmed', 'preparing', 'on_the_way'], true)) {
                return 'Primero debes confirmar el pedido.';
            }
            if (
                $to === 'on_the_way'
                && (
                    $locked->delivery_type !== 'delivery'
                    || $locked->delivery_user_id === null
                    || $locked->delivery_latitude === null
                    || $locked->delivery_longitude === null
                )
            ) {
                return 'Debes asignar un repartidor y configurar el destino antes de iniciar la entrega.';
            }
            $locked->payment_status = 'verified';
            $locked->payment_verified_at = now();
            $locked->payment_verified_by = auth()->id();

            $locked->save();

            return null;
        }, 3);

        if ($error !== null) {
            return back()->withErrors(['payment' => $error]);
        }

        return back()->with(
            'success',
            'Cobro en efectivo registrado correctamente.'
        );
    }

    public function updateStatus(
        Request $request,
        Order $order
    ): RedirectResponse {
        $data = $request->validate([
            'status' => [
                'required',
                Rule::in(array_keys(self::STATUSES)),
            ],
            'comment' => [
                'nullable', 'string', 'max:1000',
            ],
            'cancellation_reason' => [
                'required_if:status,cancelled',
                'nullable', 'string', 'max:1000',
            ],
        ]);

        $error = DB::transaction(function () use ($order, $data) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            $from = $locked->status;
            $to = $data['status'];

            if (!in_array($to, self::TRANSITIONS[$from] ?? [], true)) {
                return 'Transición de estado no permitida.';
            }

            if ($to !== 'cancelled') {
                if (
                    in_array($locked->payment_method, ['yape', 'plin'], true)
                    && $locked->payment_status !== 'verified'
                ) {
                    return 'Primero debes verificar el pago por Yape o Plin.';
                }

                if (
                    $locked->payment_method === 'cash'
                    && $locked->payment_status === 'rejected'
                ) {
                    return 'El pago de este pedido está rechazado.';
                }
            }

            // Ningún pedido se entrega sin pago registrado.
            if (
                $to === 'delivered'
                && $locked->payment_status !== 'verified'
            ) {
                return 'Debes registrar el cobro antes de marcar el pedido como entregado.';
            }

            // Un pedido cobrado no se cancela directamente.
            // Requiere un proceso separado de devolución.
            if (
                $to === 'cancelled'
                && $locked->payment_status === 'verified'
            ) {
                return 'El pedido ya está pagado. Primero debe gestionarse la devolución.';
            }

            $timestamps = [
                'confirmed' => 'confirmed_at',
                'preparing' => 'preparing_at',
                'on_the_way' => 'on_the_way_at',
                'delivered' => 'delivered_at',
                'cancelled' => 'cancelled_at',
            ];

            $locked->status = $to;
            if (in_array($to, ['delivered', 'cancelled'], true)) {
                $locked->driver_tracking_stopped_at = now();
                $locked->driver_latitude = null;
                $locked->driver_longitude = null;
                $locked->driver_location_updated_at = null;
            }
            if (isset($timestamps[$to])) {
                $locked->{$timestamps[$to]} = now();
            }

            if ($to === 'cancelled') {
                $locked->cancellation_reason =
                    $data['cancellation_reason'];
            }

            $locked->save();

            $locked->statusHistories()->create([
                'from_status' => $from,
                'to_status' => $to,
                'comment' => $to === 'cancelled'
                    ? $data['cancellation_reason']
                    : ($data['comment'] ?? null),
                'changed_by' => auth()->id(),
            ]);

            return null;
        }, 3);

        if ($error !== null) {
            return back()->withErrors(['status' => $error]);
        }

        return back()->with('success', 'Estado actualizado correctamente.');
    }

    public function assignDriver(
        Request $request,
        Order $order
    ): RedirectResponse {
        $data = $request->validate([
            'delivery_user_id' => [
                'required',
                'integer',
                Rule::exists('users', 'id')
                    ->where('role', 'delivery'),
            ],
        ]);

        $error = DB::transaction(function () use ($order, $data) {
            $locked = Order::query()
                ->whereKey($order->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->delivery_type !== 'delivery') {
                return 'Este pedido no requiere reparto.';
            }

            if (!in_array($locked->status, ['confirmed', 'preparing'], true)) {
                return 'Solo puedes asignar repartidores a pedidos confirmados o en preparación.';
            }

            $locked->delivery_user_id = $data['delivery_user_id'];

            // Al reasignar, invalidamos cualquier ubicación anterior.
            $locked->driver_latitude = null;
            $locked->driver_longitude = null;
            $locked->driver_location_updated_at = null;
            $locked->driver_tracking_started_at = null;
            $locked->driver_tracking_stopped_at = null;

            $locked->save();

            return null;
        }, 3);

        if ($error !== null) {
            return back()->withErrors(['delivery_user_id' => $error]);
        }

        return back()->with('success', 'Repartidor asignado correctamente.');
    }
}