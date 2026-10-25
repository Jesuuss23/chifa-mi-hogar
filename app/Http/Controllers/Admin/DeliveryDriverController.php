<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class DeliveryDriverController extends Controller
{
    public function index(): View
    {
        return view('admin.delivery-drivers.index', [
            'drivers' => User::query()
                ->where('role', 'delivery')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)],
        ]);

        $driver = new User();

        $driver->name = $data['name'];
        $driver->email = $data['email'];
        $driver->password = Hash::make($data['password']);
        $driver->role = 'delivery';

        $driver->save();

        return redirect()
            ->route('admin.delivery-drivers.index')
            ->with('success', 'Repartidor creado correctamente.');
    }
}
