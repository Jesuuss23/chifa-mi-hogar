<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class CheckCustomerPasswordVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user?->role === 'customer'
            && (int) $request->session()->get('customer_password_version', 0) !== (int) $user->password_version) {
            $cart = $request->session()->get('cart');
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            if ($cart !== null) {
                $request->session()->put('cart', $cart);
            }
            return redirect()->route('login')->with('password_status', 'Tu contraseña cambió. Inicia sesión nuevamente.');
        }

        $response = $next($request);
        // Capture direct/social logins completed later in this request too.
        if ($request->user()?->role === 'customer') {
            $request->session()->put('customer_password_version', (int) $request->user()->password_version);
        }
        return $response;
    }
}
