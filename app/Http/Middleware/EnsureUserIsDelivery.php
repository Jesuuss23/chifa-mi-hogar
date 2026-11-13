<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsDelivery
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!$request->user()?->canDeliverOrders()) {
            abort(403, 'Acceso exclusivo para repartidores y administradores.');
        }

        return $next($request);
    }
}
