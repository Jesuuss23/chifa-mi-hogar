<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CustomerAuthController extends Controller
{
    public function showRegister()
    {
        return view('customer.auth.register');
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $user = new User();

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->password = Hash::make($data['password']);
        $user->role = 'customer';

        $user->save();

        // Nunca aceptamos un rol enviado desde el formulario.
        // La base de datos asigna customer por defecto.
        Auth::login($user);

        $request->session()->regenerate();

        return redirect()->route('customer.account');
    }

    public function showLogin()
    {
        return view('customer.auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $valid = Auth::attempt([
            ...$credentials,
            'role' => 'customer',
        ]);

        if (! $valid) {
            throw ValidationException::withMessages([
                'email' => 'Correo o contraseña incorrectos.',
            ]);
        }

        $request->session()->regenerate();

        return redirect()->intended(route('customer.account'));
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('storefront.index');
    }

    public function account(Request $request)
    {
        $orders = $request->user()
            ->orders()
            ->with('rating')
            ->latest()
            ->paginate(10);

        return view('customer.account', compact('orders'));
    }
}