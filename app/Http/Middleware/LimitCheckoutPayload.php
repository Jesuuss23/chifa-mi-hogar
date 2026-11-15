<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitCheckoutPayload
{
    public const MAX_BYTES = 131072;

    public function handle(Request $request, Closure $next): Response
    {
        // These routes accept ordinary forms and JSON, never file uploads.
        // PHP can consume multipart input before getContent(), so disallow that bypass.
        abort_if(str_starts_with(strtolower($request->header('Content-Type', '')), 'multipart/'), 415, 'El carrito no admite archivos adjuntos.');
        // Check actual bytes, not a supplied Content-Length header.
        // Bound work before Laravel expands nested item/extra validation rules.
        abort_if(strlen($request->getContent()) > self::MAX_BYTES, 413, 'La solicitud del carrito es demasiado grande.');

        return $next($request);
    }
}
