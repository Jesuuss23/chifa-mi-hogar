<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! $request->user()
            || $request->user()->role !== 'customer'
        ) {
            abort(403, 'Acceso exclusivo para clientes.');
        }

        return $next($request);
    }
}