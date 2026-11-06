<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\StorefrontController;
use App\Models\StoreSetting;
use App\Services\StorefrontTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AppearanceController extends Controller
{
    public function index(StorefrontTheme $themes): View
    {
        return view('admin.appearance.index', [
            'themes' => StorefrontTheme::THEMES,
            'activeTheme' => $themes->current(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', Rule::in(array_keys(StorefrontTheme::THEMES))],
        ]);

        StoreSetting::current()->forceFill(['storefront_theme' => $validated['theme']])->save();

        return to_route('admin.appearance.index')
            ->with('success', 'Apariencia actualizada para tus clientes.');
    }

    public function preview(Request $request, StorefrontController $storefront)
    {
        // Defense in depth: a visual document cannot execute scripts or submit forms,
        // including when opened directly instead of inside the preview dialog.
        return response($storefront->index($request)->render())
            ->header('Content-Security-Policy', 'sandbox allow-same-origin')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }
}
