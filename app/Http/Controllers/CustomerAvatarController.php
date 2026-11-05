<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\CustomerAvatarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CustomerAvatarController extends Controller
{
    public function show(Request $request, CustomerAvatarService $avatars)
    {
        $user = $request->user();
        abort_unless($avatars->ownsPath($user->avatar_path, $user->id), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($user->avatar_path), 404);

        return response($disk->get($user->avatar_path), 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store, max-age=0',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="perfil.webp"',
        ]);
    }

    public function update(Request $request, CustomerAvatarService $avatars)
    {
        $request->validateWithBag('avatar', [
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048',
                'dimensions:min_width=32,min_height=32,max_width=2048,max_height=2048'],
        ], [
            'avatar.required' => 'Selecciona una foto.',
            'avatar.image' => 'Selecciona una imagen válida.',
            'avatar.mimes' => 'Usa una imagen JPG, PNG o WebP.',
            'avatar.max' => 'La foto no debe superar los 2 MB.',
            'avatar.dimensions' => 'Usa una foto entre 32 y 2048 píxeles por lado.',
        ]);
        $userId = $request->user()->id;
        $newPath = null;
        try {
            $newPath = $avatars->store($request->file('avatar'), $userId);
            $oldPath = DB::transaction(function () use ($userId, $newPath) {
                $user = User::whereKey($userId)->where('role', 'customer')->lockForUpdate()->firstOrFail();
                $oldPath = $user->avatar_path;
                $user->forceFill(['avatar_path' => $newPath])->save();
                return $oldPath;
            }, 3);
        } catch (Throwable) {
            $avatars->discard($newPath, $userId);
            Log::warning('Customer avatar could not be saved.');
            throw ValidationException::withMessages([
                'avatar' => 'No pudimos guardar la foto. Prueba con otra imagen o inténtalo más tarde.',
            ])->errorBag('avatar');
        }
        // Delete only the previous file after the new reference is committed.
        $avatars->discard($oldPath, $userId);
        return redirect(route('customer.account').'#account-avatar')
            ->with('avatar_status', 'Tu foto de perfil se guardó correctamente.');
    }

    public function destroy(Request $request, CustomerAvatarService $avatars)
    {
        $userId = $request->user()->id;
        $oldPath = DB::transaction(function () use ($userId) {
            $user = User::whereKey($userId)->where('role', 'customer')->lockForUpdate()->firstOrFail();
            $oldPath = $user->avatar_path;
            $user->forceFill(['avatar_path' => null])->save();
            return $oldPath;
        }, 3);
        $avatars->discard($oldPath, $userId);

        return redirect(route('customer.account').'#account-avatar')
            ->with('avatar_status', 'Tu foto de perfil se quitó de tu cuenta.');
    }
}
