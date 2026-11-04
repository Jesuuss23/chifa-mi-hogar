<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class GoogleCustomerLogin
{
    public static function enabled(): bool
    {
        return config('google_login.enabled')
            && filled(config('google_login.client_id'))
            && filled(config('google_login.client_secret'))
            && filter_var(config('google_login.redirect_uri'), FILTER_VALIDATE_URL)
            && parse_url(config('google_login.redirect_uri'), PHP_URL_SCHEME) === 'https';
    }

    public function authorizationUrl(string $state, string $verifier): string
    {
        return 'https://accounts.google.com/o/oauth2/v2/auth?'.http_build_query([
            'client_id' => config('google_login.client_id'),
            'redirect_uri' => config('google_login.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
            'code_challenge_method' => 'S256',
            'prompt' => 'select_account',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function profile(string $code, string $verifier): array
    {
        try {
            $token = Http::asForm()->acceptJson()->connectTimeout(3)->timeout(10)
                ->withoutRedirecting()->post('https://oauth2.googleapis.com/token', [
                    'client_id' => config('google_login.client_id'),
                    'client_secret' => config('google_login.client_secret'),
                    'redirect_uri' => config('google_login.redirect_uri'),
                    'grant_type' => 'authorization_code',
                    'code' => $code,
                    'code_verifier' => $verifier,
                ]);
            $access = $token->json('access_token');
            if (! $token->successful() || ! is_string($access) || $access === '') {
                $this->reject();
            }

            // Obtain identity directly from Google's authenticated HTTPS endpoint.
            // Never trust decoded, unverified ID tokens or browser-supplied profile data.
            $response = Http::withToken($access)->acceptJson()->connectTimeout(3)->timeout(10)
                ->withoutRedirecting()->get('https://openidconnect.googleapis.com/v1/userinfo');
            $profile = $response->json();
            if (! $response->successful() || ! is_array($profile)
                || ! is_string($profile['sub'] ?? null) || $profile['sub'] === '' || strlen($profile['sub']) > 255
                || ($profile['email_verified'] ?? null) !== true
                || ! is_string($profile['email'] ?? null) || strlen($profile['email']) > 255
                || ! filter_var($profile['email'], FILTER_VALIDATE_EMAIL)) {
                $this->reject();
            }

            return [
                'id' => $profile['sub'],
                'email' => Str::lower(trim($profile['email'])),
                'name' => is_string($profile['name'] ?? null) && trim($profile['name']) !== ''
                    ? Str::limit(trim($profile['name']), 150, '') : 'Cliente',
            ];
        } catch (ConnectionException) {
            $this->reject();
        }
    }

    public function resolve(array $profile, ?int $linkUserId): User
    {
        return DB::transaction(function () use ($profile, $linkUserId) {
            $identity = DB::table('customer_social_accounts')
                ->where('provider', 'google')->where('provider_id', $profile['id'])->first();

            if ($identity) {
                $user = User::query()->lockForUpdate()->find($identity->user_id);
                if (! $user || $user->role !== 'customer'
                    || ($linkUserId !== null && $user->id !== $linkUserId)) {
                    $this->reject();
                }
                return $user;
            }

            if ($linkUserId !== null) {
                $user = User::query()->lockForUpdate()->find($linkUserId);
                if (! $user || $user->role !== 'customer'
                    || Str::lower($user->email) !== $profile['email']
                    || DB::table('customer_social_accounts')->where('user_id', $user->id)
                        ->where('provider', 'google')->exists()) {
                    $this->reject('No se pudo vincular Google. Usa la cuenta de Google con el mismo correo de tu perfil.');
                }
            } else {
                if (User::query()->whereRaw('LOWER(email) = ?', [$profile['email']])->exists()) {
                    $this->reject('No se pudo crear una cuenta con ese correo. Si ya tienes cuenta, entra con tu contraseña y vincula Google desde Mi cuenta.');
                }
                $user = new User();
                $user->forceFill([
                    'name' => $profile['name'],
                    'email' => $profile['email'],
                    'password' => Str::random(64),
                    'role' => 'customer',
                    'email_verified_at' => now(),
                ])->save();
            }

            DB::table('customer_social_accounts')->insert([
                'user_id' => $user->id, 'provider' => 'google', 'provider_id' => $profile['id'],
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $user;
        }, 3);
    }

    private function reject(string $message = 'No pudimos verificar tu cuenta de Google. Inténtalo nuevamente.'): never
    {
        throw ValidationException::withMessages(['google' => $message]);
    }
}
