<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class EnsureAdminMobileToken
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        // A browser session or a Sanctum transient token is not mobile authentication.
        abort_unless($request->bearerToken() && $token instanceof PersonalAccessToken, 401);
        abort_unless($user->isAdmin() && $token->can('admin-mobile:read'), 403);
        return $next($request);
    }
}
