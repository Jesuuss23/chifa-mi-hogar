<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CustomerAvatarService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100']]);
        $search = trim($data['q'] ?? '');
        $customers = User::query()->where('role', 'customer')
            ->select(['id', 'name', 'email', 'created_at'])
            ->with('customerProfile:id,user_id,phone')
            ->when($search !== '', function ($query) use ($search) {
                // Bind the value and treat LIKE wildcards as literal search characters.
                $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';
                $query->where(function ($query) use ($pattern) {
                    $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereRaw("LOWER(email) LIKE ? ESCAPE '!'", [$pattern])
                        ->orWhereHas('customerProfile', fn ($profile) =>
                            $profile->whereRaw("phone LIKE ? ESCAPE '!'", [$pattern]));
                });
            })
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate(25)->appends(['q' => $search]);

        return view('admin.customers.index', compact('customers', 'search'));
    }

    public function show(Request $request, string $customer, CustomerAvatarService $avatars)
    {
        $customer = User::query()->where('role', 'customer')
            ->select(['id', 'name', 'email', 'email_verified_at', 'created_at', 'avatar_path'])
            ->with([
                'customerProfile',
                'socialAccounts' => fn ($query) => $query
                    ->select(['id', 'user_id', 'provider'])->whereIn('provider', ['google', 'facebook']),
            ])->findOrFail($customer);
        $hasAvatar = $avatars->ownsPath($customer->avatar_path, $customer->id)
            && Storage::disk('local')->exists($customer->avatar_path);
        $profile = $customer->customerProfile;
        $lat = $profile?->delivery_latitude;
        $lng = $profile?->delivery_longitude;
        $mapUrl = is_numeric($lat) && is_numeric($lng)
            && (float) $lat >= -90 && (float) $lat <= 90
            && (float) $lng >= -180 && (float) $lng <= 180
            ? 'https://www.google.com/maps?q='.rawurlencode($lat.','.$lng) : null;
        $view = $request->ajax() ? 'admin.customers.details' : 'admin.customers.show';

        return response()->view($view, compact('customer', 'profile', 'hasAvatar', 'mapUrl'))
            ->header('X-Customer-Details', '1');
    }

    public function avatar(string $customer, CustomerAvatarService $avatars)
    {
        $customer = User::query()->where('role', 'customer')
            ->select(['id', 'avatar_path'])->findOrFail($customer);
        abort_unless($avatars->ownsPath($customer->avatar_path, $customer->id), 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($customer->avatar_path), 404);

        return response($disk->get($customer->avatar_path), 200, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="perfil.webp"',
        ]);
    }
}
