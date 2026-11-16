<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminPushDevice;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;

class MobilePushController extends Controller
{
    public function store(Request $request)
    {
        abort_unless(config('admin_push.enabled'), 503, 'Los avisos no están habilitados.');
        $data = $request->validate([
            'fcm_token' => ['required', 'string', 'min:20', 'max:4096', 'regex:/\\A[A-Za-z0-9_:\\-]+\\z/'],
        ]);
        try {
            DB::transaction(function () use ($request, $data) {
                $token = PersonalAccessToken::whereKey($request->user()->currentAccessToken()->id)
                    ->lockForUpdate()->first();
                abort_unless($token && $token->expires_at?->isFuture()
                    && str_starts_with($token->name, 'admin-mobile:'), 401);
                $device = AdminPushDevice::firstOrNew(['personal_access_token_id' => $token->id]);
                $device->fcm_token = $data['fcm_token'];
                $device->token_hash = hash('sha256', $data['fcm_token']);
                $device->save();
            });
        } catch (UniqueConstraintViolationException) {
            abort(409, 'Este dispositivo ya está vinculado a otra sesión.');
        }
        return response()->noContent();
    }

    public function destroy(Request $request)
    {
        // The same lock serializes registration and opt-out for this session.
        DB::transaction(function () use ($request) {
            $token = PersonalAccessToken::whereKey($request->user()->currentAccessToken()->id)
                ->lockForUpdate()->first();
            if ($token) {
                AdminPushDevice::where('personal_access_token_id', $token->id)->delete();
            }
        });
        return response()->noContent();
    }
}
