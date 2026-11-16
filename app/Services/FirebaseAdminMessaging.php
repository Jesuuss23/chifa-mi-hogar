<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class FirebaseAdminMessaging
{
    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    /** Returns sanitized outcomes only; never throws an HTTP exception containing credentials. */
    public function send(string $deviceToken, int $orderId): array
    {
        try {
            $credentials = $this->credentials();
            $cacheKey = 'admin-fcm-oauth:'.hash('sha256', $credentials['client_email'].$credentials['private_key_id']);
            $access = Cache::remember($cacheKey, 3000, fn () => $this->accessToken($credentials));
        } catch (\Throwable) {
            return ['status' => 'failed', 'result' => 'credentials_or_oauth'];
        }

        try {
            $response = Http::withToken($access)->acceptJson()->withoutRedirecting()
                ->connectTimeout(5)->timeout(15)
                ->post('https://fcm.googleapis.com/v1/projects/'.$credentials['project_id'].'/messages:send', [
                    'message' => [
                        'token' => $deviceToken,
                        'data' => ['type' => 'new_order', 'order_id' => (string) $orderId],
                        'android' => ['priority' => 'high', 'ttl' => '900s',
                            'restricted_package_name' => 'com.chifamihogar.admin'],
                    ],
                ]);
        } catch (\Throwable) {
            // It may have reached FCM: don't automatically resend an uncertain result.
            return ['status' => 'uncertain', 'result' => 'network_uncertain'];
        }
        if ($response->successful() && is_string($response->json('name')) && $response->json('name') !== '') {
            return ['status' => 'sent', 'result' => 'accepted'];
        }
        foreach ((array) $response->json('error.details', []) as $detail) {
            if (($detail['@type'] ?? '') === 'type.googleapis.com/google.firebase.fcm.v1.FcmError'
                && ($detail['errorCode'] ?? '') === 'UNREGISTERED') {
                return ['status' => 'invalid', 'result' => 'unregistered'];
            }
        }
        if ($response->status() === 401) Cache::forget($cacheKey);
        if (in_array($response->status(), [429, 503], true)) {
            $header = $response->header('Retry-After');
            $seconds = ctype_digit((string) $header) ? (int) $header
                : max(0, (strtotime((string) $header) ?: time()) - time());
            return ['status' => 'retry', 'result' => 'http_'.$response->status(),
                'delay' => max(60, min(86400, $seconds))];
        }
        return ['status' => 'failed', 'result' => 'http_'.$response->status()];
    }

    private function credentials(): array
    {
        $path = config('admin_push.credentials');
        if (! is_string($path) || ! is_readable($path) || filesize($path) > 65536) {
            throw new RuntimeException('Firebase credentials unavailable');
        }
        $data = json_decode(file_get_contents($path), true, 16, JSON_THROW_ON_ERROR);
        foreach (['project_id', 'client_email', 'private_key', 'private_key_id'] as $key) {
            if (! isset($data[$key]) || ! is_string($data[$key]) || $data[$key] === '') {
                throw new RuntimeException('Invalid Firebase credentials');
            }
        }
        if (($data['type'] ?? '') !== 'service_account'
            || ! preg_match('/\\A[a-z][a-z0-9-]{4,61}[a-z0-9]\\z/', $data['project_id'])) {
            throw new RuntimeException('Invalid Firebase project');
        }
        return $data;
    }

    private function accessToken(array $credentials): string
    {
        $encode = static fn (string $value) => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
        $header = $encode(json_encode(['alg' => 'RS256', 'typ' => 'JWT', 'kid' => $credentials['private_key_id']], JSON_THROW_ON_ERROR));
        $claims = $encode(json_encode([
            'iss' => $credentials['client_email'],
            'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
            'aud' => self::TOKEN_URL, 'iat' => time(), 'exp' => time() + 3600,
        ], JSON_THROW_ON_ERROR));
        $unsigned = $header.'.'.$claims;
        if (! openssl_sign($unsigned, $signature, $credentials['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Firebase signing failed');
        }
        $response = Http::asForm()->acceptJson()->withoutRedirecting()->connectTimeout(5)->timeout(15)
            ->post(self::TOKEN_URL, [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $unsigned.'.'.$encode($signature),
            ]);
        $token = $response->json('access_token');
        if (! $response->successful() || ! is_string($token) || $token === ''
            || (int) $response->json('expires_in') < 3300) {
            throw new RuntimeException('Firebase authentication failed');
        }
        return $token;
    }
}
