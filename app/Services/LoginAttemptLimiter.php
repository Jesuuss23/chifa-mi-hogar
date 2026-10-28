<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class LoginAttemptLimiter
{
    private function key(Request $request, string $email): string
    {
        // Compartido entre portales, sin almacenar el correo en texto plano.
        return 'login-failures:'.hash('sha256', mb_strtolower(trim($email)).'|'.$request->ip());
    }

    public function ensureAllowed(Request $request, string $email): void
    {
        $key = $this->key($request, $email);

        if (RateLimiter::tooManyAttempts($key, 5)) {
            $seconds = RateLimiter::availableIn($key);
            throw ValidationException::withMessages([
                'email' => "Demasiados intentos. Inténtalo de nuevo en {$seconds} segundos.",
            ]);
        }
    }

    public function failed(Request $request, string $email): void
    {
        // Ventana de 15 minutos desde el primer fallo, no desde el último.
        RateLimiter::hit($this->key($request, $email), 900);
    }

    public function clear(Request $request, string $email): void
    {
        RateLimiter::clear($this->key($request, $email));
    }
}
