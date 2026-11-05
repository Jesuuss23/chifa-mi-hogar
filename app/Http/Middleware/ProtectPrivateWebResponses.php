<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ProtectPrivateWebResponses
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Match route names, not user-controlled paths or query parameters.
        if ($request->routeIs(
            'cart.*', 'checkout.*', 'customer.*', 'login',
            'admin.*', 'delivery.*', 'orders.tracking.*'
        )) {
            $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            $response->headers->set('Cache-Control', 'private, no-store');
            $response->headers->set('Referrer-Policy', 'no-referrer');
        }

        return $response;
    }
}
