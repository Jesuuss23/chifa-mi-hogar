<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\StoreSetting;
use Carbon\CarbonImmutable;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $today = CarbonImmutable::now('America/Lima')->startOfDay();
        // Orders are stored in the application's timezone (UTC by default).
        $start = $today->setTimezone(config('app.timezone'));
        $end = $today->addDay()->setTimezone(config('app.timezone'));

        return view('admin.dashboard', [
            'ordersToday' => Order::query()
                ->where('created_at', '>=', $start)
                ->where('created_at', '<', $end)
                ->count(),
            'salesToday' => Order::query()
                ->where('status', 'delivered')
                ->where('payment_status', 'verified')
                ->where('delivered_at', '>=', $start)
                ->where('delivered_at', '<', $end)
                ->sum('total'),
            'pendingOrders' => Order::query()->where('status', 'pending')->count(),
            'kitchenOpen' => StoreSetting::current()->accepting_orders,
            'recentOrders' => Order::query()
                ->select(['id', 'code', 'customer_name', 'created_at', 'status', 'total'])
                ->latest('created_at')->latest('id')->limit(5)->get(),
            'statuses' => [
                'pending' => 'Pendiente',
                'confirmed' => 'Confirmado',
                'preparing' => 'Preparando',
                'on_the_way' => 'En camino',
                'delivered' => 'Entregado',
                'cancelled' => 'Cancelado',
            ],
        ]);
    }
}
