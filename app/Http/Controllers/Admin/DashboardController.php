<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        return view('admin.dashboard', [
            'ordersToday' => 0,
            'salesToday' => 0,
            'pendingOrders' => 0,
            'kitchenOpen' => false,
        ]);
    }
}