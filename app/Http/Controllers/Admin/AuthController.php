<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\LoginAttemptLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showLogin()
    {
        return view('admin.auth.login');
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
            'role' => 'admin',
        ]);

        if (! $valid) {
            $limiter->failed($request, $credentials['email']);

            throw ValidationException::withMessages([
                'email' => 'Credenciales incorrectas.',
            ]);
        }

        $limiter->clear($request, $credentials['email']);
        $request->session()->regenerate();

        return redirect()->route('admin.dashboard');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}