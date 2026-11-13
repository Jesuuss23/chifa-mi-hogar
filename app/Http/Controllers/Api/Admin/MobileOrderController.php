<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\AdminMobileOrderResource;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class MobileOrderController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['pending', 'confirmed', 'preparing', 'on_the_way', 'delivered', 'cancelled'])],
            'search' => ['sometimes', 'string', 'max:100'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ]);
        $query = Order::query();
        if (isset($data['status'])) {
            $query->where('status', $data['status']);
        }
        if (! empty($data['search'])) {
            $search = '%'.$data['search'].'%';
            $query->where(fn ($q) => $q->whereLike('code', $search)
                ->orWhereLike('customer_name', $search));
        }
        return AdminMobileOrderResource::collection(
            $query->orderByDesc('created_at')->orderByDesc('id')
                ->paginate($data['per_page'] ?? 20)->withQueryString()
        );
    }

    public function show(Order $order)
    {
        $order->load(['items.extras', 'items.comboComponents', 'deliveryUser']);
        return new AdminMobileOrderResource($order);
    }
}
