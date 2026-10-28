<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Services\LoginAttemptLimiter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('delivery.login');
    }

    public function login(Request $request, LoginAttemptLimiter $limiter): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $limiter->ensureAllowed($request, $credentials['email']);

        $credentials['role'] = 'delivery';

        if (!Auth::attempt($credentials)) {
            $limiter->failed($request, $credentials['email']);
            return back()
                ->withErrors(['email' => 'Credenciales incorrectas.'])
                ->onlyInput('email');
        }

        $limiter->clear($request, $credentials['email']);
        $request->session()->regenerate();

        return redirect()->route('delivery.orders.index');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('delivery.login');
    }
}
