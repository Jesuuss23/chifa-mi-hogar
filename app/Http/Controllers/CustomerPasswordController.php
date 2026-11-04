<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Notifications\CustomerResetPassword;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

class CustomerPasswordController extends Controller
{
    public function request()
    {
        return response()->view('customer.auth.password', ['reset' => false])
            ->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function email(Request $request)
    {
        // Never emit usable reset links into log/array or a failover containing log.
        $transport = config('mail.mailers.'.config('mail.default').'.transport');
        abort_unless(in_array($transport, ['smtp', 'sendmail', 'ses', 'postmark', 'resend', 'mailgun'], true),
            503, 'El envío de correos no está disponible. Inténtalo más tarde.');
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        $matches = User::whereRaw('LOWER(email) = ?', [Str::lower(trim($data['email']))])->limit(2)->get();
        if ($matches->count() === 1 && $matches->first()->role === 'customer') {
            $user = $matches->first();
            try {
                Password::broker('users')->sendResetLink(
                    ['email' => $user->email, 'role' => 'customer'],
                    fn ($recipient, $token) => $recipient->notify(new CustomerResetPassword($token))
                );
            } catch (TransportExceptionInterface) {
                // No recipient, token, credentials or raw exception in logs.
                Log::warning('Customer password reset email could not be delivered.');
            }
        }

        return back()->with('password_status', 'Si el correo corresponde a una cuenta de cliente, recibirás un enlace para establecer tu contraseña. Revisa también spam.');
    }

    public function form(Request $request, string $token)
    {
        abort_unless(strlen($token) <= 255, 404);
        $email = $request->query('email');
        return response()->view('customer.auth.password', [
            'reset' => true, 'token' => $token,
            'email' => is_string($email) ? Str::limit($email, 255, '') : '',
        ])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer');
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', PasswordRule::min(8)],
        ]);
        // Serialize concurrent uses before token validation and consumption.
        $status = DB::transaction(function () use ($data) {
            $user = User::where('email', $data['email'])->where('role', 'customer')->lockForUpdate()->first();
            if (! $user) {
                return Password::INVALID_TOKEN;
            }
            return Password::broker('users')->reset(
                [...$data, 'role' => 'customer'],
                function (User $user, string $password) {
                    $this->savePassword($user, $password);
                    event(new PasswordReset($user));
                }
            );
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace no es válido o ha caducado. Solicita uno nuevo.']);
        }

        return redirect()->route('login')->with('password_status', 'Contraseña guardada. Ya puedes iniciar sesión con tu correo y contraseña.');
    }

    public function update(Request $request)
    {
        $data = $request->validateWithBag('changePassword', [
            'current_password' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'max:255', 'confirmed', PasswordRule::min(8)],
        ]);
        $user = DB::transaction(function () use ($request, $data) {
            $user = User::whereKey($request->user()->id)->where('role', 'customer')->lockForUpdate()->firstOrFail();
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages(['current_password' => 'La contraseña actual no es correcta.'])->errorBag('changePassword');
            }
            $this->savePassword($user, $data['password']);
            Password::broker('users')->deleteToken($user);
            return $user;
        });

        $request->user()->setRawAttributes($user->getAttributes(), true);
        $request->session()->put('customer_password_version', (int) $user->password_version);
        $request->session()->forget(['google_login', 'facebook_login']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.account')->with('password_status', 'Contraseña actualizada. Las otras sesiones deberán iniciar sesión de nuevo.');
    }

    private function savePassword(User $user, string $password): void
    {
        $user->forceFill([
            'password' => $password,
            'remember_token' => Str::random(60),
            'password_version' => ((int) $user->password_version) + 1,
        ])->save();
    }
}
