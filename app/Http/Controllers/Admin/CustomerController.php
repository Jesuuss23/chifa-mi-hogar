<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:120']]);
        $search = trim($data['q'] ?? '');
        $query = User::query()->where('role', 'customer')
            ->select(['id', 'name', 'email', 'created_at'])
            ->with('customerProfile:id,user_id,phone');
        if ($search !== '') {
            $term = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
            $query->where(function ($query) use ($term) {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$term])
                    ->orWhereHas('customerProfile', fn ($profile) =>
                        $profile->whereRaw("phone LIKE ? ESCAPE '!'", [$term]));
            });
        }
        $customers = $query->orderByDesc('id')->paginate(20)->withQueryString();

        return view('admin.customers.index', compact('customers', 'search'));
    }

    public function show(int $customer)
    {
        $customer = User::query()->where('role', 'customer')
            ->select(['id', 'name', 'email', 'created_at'])
            ->with('customerProfile:id,user_id,phone,delivery_address,delivery_reference,delivery_latitude,delivery_longitude')
            ->findOrFail($customer);

        return view('admin.customers.show', compact('customer'));
    }
}
