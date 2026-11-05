<?php

namespace App\Http\Controllers;

use App\Services\FacebookCustomerLogin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerFacebookAuthController extends Controller
{
    public function start(Request $request, FacebookCustomerLogin $facebook): RedirectResponse
    {
        abort_unless(FacebookCustomerLogin::enabled(), 404);
        $linkUserId = null;
        if ($request->user()) {
            abort_unless($request->user()->role === 'customer', 403);
            $data = $request->validate(['password' => ['required', 'string']]);
            if (! Hash::check($data['password'], $request->user()->password)) {
                return redirect()->route('customer.account')->withErrors(['facebook' => 'La contraseña actual no es correcta.']);
            }
            $linkUserId = $request->user()->id;
        }

        $state = Str::random(64);
        $request->session()->put('facebook_login', [
            'state' => $state,
            'created_at' => now()->timestamp, 'user_id' => $linkUserId,
            'access_version' => $request->user() ? (int) $request->user()->password_version : null,
        ]);

        return $this->privateResponse(redirect()->away($facebook->authorizationUrl($state)));
    }

    public function callback(Request $request, FacebookCustomerLogin $facebook): RedirectResponse
    {
        abort_unless(FacebookCustomerLogin::enabled(), 404);
        $pending = $request->session()->pull('facebook_login');
        $failure = fn (string $message) => $this->privateResponse(
            redirect()->route($request->user()?->role === 'customer' ? 'customer.account' : 'login')
                ->withErrors(['facebook' => $message])
        );
        $state = $request->query('state');
        if (! is_array($pending) || ! is_string($state)
            || ! hash_equals($pending['state'], $state)
            || now()->timestamp - $pending['created_at'] < 0
            || now()->timestamp - $pending['created_at'] > 600
            || $request->user()?->id !== $pending['user_id']
            || ! Cache::add('facebook-login-used:'.hash('sha256', $state), true, 660)) {
            return $failure('La solicitud de acceso expiró o no es válida. Vuelve a intentarlo.');
        }

        $code = $request->query('code');
        if ($request->has('error') || ! is_string($code) || $code === '' || strlen($code) > 4096) {
            return $failure('No se completó el acceso con Facebook. Puedes volver a intentarlo o usar tu correo y contraseña.');
        }

        try {
            $profile = $facebook->profile($code);
            $user = $facebook->resolve($profile, $pending['user_id'],
                $pending['access_version'] ?? ($request->user() ? (int) $request->user()->password_version : null));
        } catch (ValidationException $error) {
            return $failure($error->errors()['facebook'][0]);
        } catch (UniqueConstraintViolationException) {
            return $failure('No se pudo completar el acceso. Inténtalo otra vez; si ya tienes cuenta, usa tu contraseña.');
        }

        Auth::login($user);
        $request->session()->regenerate();
        // A fixed local destination avoids accepting return URLs from OAuth input.
        return $this->privateResponse(redirect()->route('customer.account')
            ->with('facebook_status', $pending['user_id'] === null
                ? 'Ingresaste con Facebook. Completa tus datos de entrega para tu próximo pedido.'
                : 'Tu cuenta de Facebook quedó vinculada.'));
    }

    private function privateResponse(RedirectResponse $response): RedirectResponse
    {
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('Referrer-Policy', 'no-referrer');
        return $response;
    }
}
