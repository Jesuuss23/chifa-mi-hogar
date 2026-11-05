<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FacebookCustomerLogin
{
    public static function enabled(): bool
    {
        return config('facebook_login.enabled')
            && preg_match('/^[0-9]+$/D', (string) config('facebook_login.app_id'))
            && filled(config('facebook_login.app_secret'))
            && preg_match('/^v[0-9]{1,3}\.[0-9]{1,2}$/D', (string) config('facebook_login.graph_version'))
            && filter_var(config('facebook_login.redirect_uri'), FILTER_VALIDATE_URL)
            && parse_url(config('facebook_login.redirect_uri'), PHP_URL_SCHEME) === 'https';
    }

    public function authorizationUrl(string $state): string
    {
        return 'https://www.facebook.com/'.config('facebook_login.graph_version').'/dialog/oauth?'.http_build_query([
            'client_id' => config('facebook_login.app_id'),
            'redirect_uri' => config('facebook_login.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'public_profile,email',
            'state' => $state,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    public function profile(string $code): array
    {
        $base = 'https://graph.facebook.com/'.config('facebook_login.graph_version');
        $appId = (string) config('facebook_login.app_id');
        $secret = (string) config('facebook_login.app_secret');

        try {
            $token = Http::acceptJson()->connectTimeout(3)->timeout(10)->withoutRedirecting()
                ->get($base.'/oauth/access_token', [
                    'client_id' => $appId,
                    'client_secret' => $secret,
                    'redirect_uri' => config('facebook_login.redirect_uri'),
                    'code' => $code,
                ]);
            $access = $token->json('access_token');
            if (! $token->successful() || ! is_string($access) || $access === '') {
                $this->reject();
            }

            // Verify the token belongs to this application and a live user identity.
            $debug = Http::withToken($appId.'|'.$secret)->acceptJson()->connectTimeout(3)->timeout(10)
                ->withoutRedirecting()->get($base.'/debug_token', ['input_token' => $access]);
            $metadata = $debug->json('data');
            if (! $debug->successful() || ! is_array($metadata)
                || ($metadata['is_valid'] ?? null) !== true
                || (! is_string($metadata['app_id'] ?? null) && ! is_int($metadata['app_id'] ?? null))
                || (string) $metadata['app_id'] !== $appId
                || ($metadata['type'] ?? null) !== 'USER'
                || ! is_string($metadata['user_id'] ?? null)
                || ! preg_match('/^[0-9]{1,255}$/D', $metadata['user_id'])
                || ! is_int($metadata['expires_at'] ?? null)
                || $metadata['expires_at'] < 0
                || ($metadata['expires_at'] !== 0 && $metadata['expires_at'] <= now()->timestamp)) {
                $this->reject();
            }
            if (isset($metadata['data_access_expires_at'])
                && (! is_int($metadata['data_access_expires_at'])
                    || $metadata['data_access_expires_at'] < 0
                    || ($metadata['data_access_expires_at'] !== 0
                        && $metadata['data_access_expires_at'] <= now()->timestamp))) {
                $this->reject();
            }

            $response = Http::withToken($access)->acceptJson()->connectTimeout(3)->timeout(10)
                ->withoutRedirecting()->get($base.'/me', [
                    'fields' => 'id,name,email',
                    'appsecret_proof' => hash_hmac('sha256', $access, $secret),
                ]);
            $profile = $response->json();
            if (! $response->successful() || ! is_array($profile)
                || ($profile['id'] ?? null) !== $metadata['user_id']) {
                $this->reject();
            }

            $email = $profile['email'] ?? null;
            $email = is_string($email) ? Str::lower(trim($email)) : null;
            if ($email === null || strlen($email) > 255 || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = null;
            }

            return [
                'id' => $profile['id'],
                'email' => $email,
                'name' => is_string($profile['name'] ?? null) && trim($profile['name']) !== ''
                    ? Str::limit(trim($profile['name']), 150, '') : 'Cliente',
            ];
        } catch (ConnectionException) {
            $this->reject();
        }
    }

    public function resolve(array $profile, ?int $linkUserId, ?int $accessVersion = null): User
    {
        return DB::transaction(function () use ($profile, $linkUserId, $accessVersion) {
            $identity = DB::table('customer_social_accounts')
                ->where('provider', 'facebook')->where('provider_id', $profile['id'])->first();

            if ($identity) {
                $user = User::query()->lockForUpdate()->find($identity->user_id);
                if (! $user || $user->role !== 'customer'
                    || ($linkUserId !== null && $accessVersion !== null && (int) $user->password_version !== $accessVersion)
                    || ! DB::table('customer_social_accounts')->where('id', $identity->id)
                        ->where('user_id', $user->id)->where('provider', 'facebook')
                        ->where('provider_id', $profile['id'])->exists()
                    || ($linkUserId !== null && $user->id !== $linkUserId)) {
                    $this->reject();
                }
                return $user;
            }

            // Returning identities may omit email; new identities must supply one.
            if ($profile['email'] === null) {
                $this->reject('Facebook no compartió un correo válido. Usa Google o crea una cuenta con correo y contraseña.');
            }

            if ($linkUserId !== null) {
                $user = User::query()->lockForUpdate()->find($linkUserId);
                if (! $user || $user->role !== 'customer'
                    || ($linkUserId !== null && $accessVersion !== null && (int) $user->password_version !== $accessVersion)
                    || Str::lower($user->email) !== $profile['email']
                    || DB::table('customer_social_accounts')->where('user_id', $user->id)
                        ->where('provider', 'facebook')->exists()) {
                    $this->reject('No se pudo vincular Facebook. Usa la cuenta de Facebook con el mismo correo de tu perfil.');
                }
            } else {
                if (User::query()->whereRaw('LOWER(email) = ?', [$profile['email']])->exists()) {
                    $this->reject('No se pudo crear una cuenta con ese correo. Si ya tienes cuenta, entra con tu contraseña y vincula Facebook desde Mi cuenta.');
                }
                $user = new User();
                $user->forceFill([
                    'name' => $profile['name'],
                    'email' => $profile['email'],
                    'password' => Str::random(64),
                    'role' => 'customer',
                    'email_verified_at' => null,
                ])->save();
            }

            DB::table('customer_social_accounts')->insert([
                'user_id' => $user->id, 'provider' => 'facebook', 'provider_id' => $profile['id'],
                'created_at' => now(), 'updated_at' => now(),
            ]);

            return $user;
        }, 3);
    }

    private function reject(string $message = 'No pudimos verificar tu cuenta de Facebook. Inténtalo nuevamente.'): never
    {
        throw ValidationException::withMessages(['facebook' => $message]);
    }
}
