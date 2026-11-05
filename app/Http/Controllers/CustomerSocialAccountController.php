<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CustomerSocialAccountController extends Controller
{
    public function destroy(Request $request, string $provider)
    {
        abort_unless(in_array($provider, ['google', 'facebook'], true), 404);
        $data = $request->validateWithBag('unlinkSocial', [
            'current_password' => ['required', 'string', 'max:255'],
            'confirm_unlink' => ['required', 'accepted'],
        ]);

        $user = DB::transaction(function () use ($request, $provider, $data) {
            $user = User::whereKey($request->user()->id)->where('role', 'customer')->lockForUpdate()->firstOrFail();
            // Knowing the current password proves the remaining direct login is usable.
            // Social-only accounts must establish one through the recovery flow first.
            if (! Hash::check($data['current_password'], $user->password)) {
                throw ValidationException::withMessages([
                    'current_password' => 'Confirma tu contraseña actual. Si solo usas Google o Facebook, establece primero una contraseña por correo.',
                ])->errorBag('unlinkSocial');
            }

            $deleted = DB::table('customer_social_accounts')->where('user_id', $user->id)
                ->where('provider', $provider)->delete();
            if ($deleted) {
                $user->forceFill([
                    'password_version' => ((int) $user->password_version) + 1,
                    'remember_token' => Str::random(60),
                ])->save();
            }

            return $user;
        }, 3);

        $request->user()->setRawAttributes($user->getAttributes(), true);
        $request->session()->put('customer_password_version', (int) $user->password_version);
        $request->session()->forget(['google_login', 'facebook_login']);
        $request->session()->regenerate();
        $request->session()->regenerateToken();

        return redirect()->route('customer.account')->with('social_status',
            'El acceso con '.ucfirst($provider).' quedó desvinculado. Puedes seguir entrando con tu correo y contraseña.');
    }
}
