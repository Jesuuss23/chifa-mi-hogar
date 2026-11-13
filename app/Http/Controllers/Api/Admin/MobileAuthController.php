<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\LoginAttemptLimiter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class MobileAuthController extends Controller
{
    public function login(Request $request, LoginAttemptLimiter $limiter)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'max:255'],
            'device_name' => ['required', 'string', 'max:80'],
        ]);
        $limiter->ensureAllowed($request, $data['email']);
        $user = User::where('email', $data['email'])->where('role', 'admin')->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            $limiter->failed($request, $data['email']);
            throw ValidationException::withMessages(['email' => 'Las credenciales no son correctas.']);
        }

        $result = DB::transaction(function () use ($user, $data) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($locked->isAdmin() && hash_equals($user->password, $locked->password), 401);
            $expires = now()->addDays(30);
            $locked->tokens()->where('name', 'like', 'admin-mobile:%')
                ->where('expires_at', '<=', now())->delete();
            // Keep at most ten mobile sessions; starting an eleventh retires the oldest.
            $oldIds = $locked->tokens()->where('name', 'like', 'admin-mobile:%')
                ->orderByDesc('id')->get(['id'])->skip(9)->pluck('id');
            if ($oldIds->isNotEmpty()) {
                $locked->tokens()->whereIn('id', $oldIds)->delete();
            }
            $token = $locked->createToken('admin-mobile:'.$data['device_name'], ['admin-mobile:read'], $expires);
            return [
                'token_type' => 'Bearer', 'access_token' => $token->plainTextToken,
                'expires_at' => $expires->toIso8601String(),
                'user' => $this->identity($locked),
            ];
        });
        $limiter->clear($request, $data['email']);
        return response()->json($result);
    }

    public function me(Request $request)
    {
        return response()->json(['data' => $this->identity($request->user())]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->noContent();
    }

    private function identity(User $user): array
    {
        return ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role];
    }
}
