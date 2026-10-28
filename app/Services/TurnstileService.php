<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class TurnstileService
{
    public function passes(string $token, ?string $ip): bool
    {
        $secret = config('turnstile.secret_key');
        $hosts = config('turnstile.hostnames', []);

        if (!is_string($secret) || trim($secret) === ''
            || !config('turnstile.site_key') || !is_array($hosts) || $hosts === []) {
            return false;
        }

        try {
            $response = Http::asForm()->connectTimeout(3)->timeout(8)
                ->post('https://challenges.cloudflare.com/turnstile/v0/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (ConnectionException $exception) {
            // No registrar el token, la clave ni los datos del formulario.
            return false;
        }

        return $response->successful()
            && $response->json('success') === true
            && $response->json('action') === 'register'
            && in_array($response->json('hostname'), $hosts, true);
    }
}
