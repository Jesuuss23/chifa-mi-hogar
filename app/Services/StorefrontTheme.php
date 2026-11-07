<?php

namespace App\Services;

use App\Models\StoreSetting;
use Illuminate\Http\Request;

class StorefrontTheme
{
    public const THEMES = [
        'formal' => 'Formal',
        'original' => 'Original',
        'christmas' => 'Navidad',
        'summer' => 'Verano',
    ];

    public function current(): string
    {
        $theme = StoreSetting::current()->storefront_theme;

        return isset(self::THEMES[$theme ?? '']) ? $theme : 'formal';
    }

    public function isPreview(Request $request): bool
    {
        return $request->routeIs('admin.appearance.preview')
            && $request->user()?->isAdmin()
            && array_key_exists((string) $request->route('theme'), self::THEMES);
    }

    public function forRequest(Request $request): string
    {
        return $this->isPreview($request) ? $request->route('theme') : $this->current();
    }
}
