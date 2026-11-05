<?php

namespace App\Http\Controllers;

use App\Services\GoogleCustomerLogin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerGoogleAuthController extends Controller
{
    public function start(Request $request, GoogleCustomerLogin $google): RedirectResponse
    {
        abort_unless(GoogleCustomerLogin::enabled(), 404);
        $linkUserId = null;
        if ($request->user()) {
            abort_unless($request->user()->role === 'customer', 403);
            $data = $request->validate(['password' => ['required', 'string']]);
            if (! Hash::check($data['password'], $request->user()->password)) {
                return redirect()->route('customer.account')->withErrors(['google' => 'La contraseña actual no es correcta.']);
            }
            $linkUserId = $request->user()->id;
        }

        $state = Str::random(64);
        $verifier = Str::random(64);
        $request->session()->put('google_login', [
            'state' => $state, 'verifier' => $verifier,
            'created_at' => now()->timestamp, 'user_id' => $linkUserId,
            'access_version' => $request->user() ? (int) $request->user()->password_version : null,
        ]);

        return $this->privateResponse(redirect()->away($google->authorizationUrl($state, $verifier)));
    }

    public function callback(Request $request, GoogleCustomerLogin $google): RedirectResponse
    {
        abort_unless(GoogleCustomerLogin::enabled(), 404);
        $pending = $request->session()->pull('google_login');
        $failure = fn (string $message) => $this->privateResponse(
            redirect()->route($request->user()?->role === 'customer' ? 'customer.account' : 'login')
                ->withErrors(['google' => $message])
        );
        $state = $request->query('state');
        if (! is_array($pending) || ! is_string($state)
            || ! hash_equals($pending['state'], $state)
            || now()->timestamp - $pending['created_at'] < 0
            || now()->timestamp - $pending['created_at'] > 600
            || $request->user()?->id !== $pending['user_id']
            || ! Cache::add('google-login-used:'.hash('sha256', $state), true, 660)) {
            return $failure('La solicitud de acceso expiró o no es válida. Vuelve a intentarlo.');
        }

        $code = $request->query('code');
        if ($request->has('error') || ! is_string($code) || $code === '' || strlen($code) > 4096) {
            return $failure('No se completó el acceso con Google. Puedes volver a intentarlo o usar tu correo y contraseña.');
        }

        try {
            $profile = $google->profile($code, $pending['verifier']);
            $user = $google->resolve($profile, $pending['user_id'],
                $pending['access_version'] ?? ($request->user() ? (int) $request->user()->password_version : null));
        } catch (ValidationException $error) {
            return $failure($error->errors()['google'][0]);
        } catch (UniqueConstraintViolationException) {
            return $failure('No se pudo completar el acceso. Inténtalo otra vez; si ya tienes cuenta, usa tu contraseña.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        // A fixed local destination avoids accepting return URLs from OAuth input.
        return $this->privateResponse(redirect()->route('customer.account')
            ->with('google_status', $pending['user_id'] === null
                ? 'Ingresaste con Google. Completa tus datos de entrega para tu próximo pedido.'
                : 'Tu cuenta de Google quedó vinculada.'));
    }

    private function privateResponse(RedirectResponse $response): RedirectResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
