<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\LoginAttemptLimiter;
use App\Services\TurnstileService;
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

    public function register(Request $request, TurnstileService $turnstile)
    {
        if (config('turnstile.enabled')) {
            $verification = $request->validate([
                'cf-turnstile-response' => ['required', 'string', 'max:2048'],
            ], [
                'cf-turnstile-response.required' => 'Completa la verificación de seguridad.',
            ]);

            if (!$turnstile->passes($verification['cf-turnstile-response'], $request->ip())) {
                throw ValidationException::withMessages([
                    'cf-turnstile-response' => 'No pudimos verificar la solicitud. Recarga la página e inténtalo de nuevo.',
                ]);
            }
        }

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

    public function login(Request $request, LoginAttemptLimiter $limiter)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $limiter->ensureAllowed($request, $credentials['email']);

        $valid = Auth::attempt([
            ...$credentials,
            'role' => 'customer',
        ]);

        if (! $valid) {
            $limiter->failed($request, $credentials['email']);

            throw ValidationException::withMessages([
                'email' => 'Correo o contraseña incorrectos.',
            ]);
        }

        $request->session()->regenerate();

        $limiter->clear($request, $credentials['email']);

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