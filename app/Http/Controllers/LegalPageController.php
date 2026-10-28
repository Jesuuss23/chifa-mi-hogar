<?php

namespace App\Http\Controllers;

use App\Models\StoreSetting;
use Illuminate\Http\Response;

class LegalPageController extends Controller
{
    public const PAGES = [
        'notice' => 'Aviso legal',
        'privacy' => 'Privacidad',
        'cookies' => 'Cookies',
    ];

    public function show(string $page): Response
    {
        $settings = StoreSetting::current();
        abort_unless($settings->legalPagesAvailable(), 404);

        return $this->renderPage($page, $settings, false);
    }

    public function preview(string $page): Response
    {
        return $this->renderPage($page, StoreSetting::current(), true)
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Cache-Control', 'private, no-store');
    }

    private function renderPage(string $page, StoreSetting $settings, bool $preview): Response
    {
        abort_unless(array_key_exists($page, self::PAGES), 404);

        return response()->view('legal.page', [
            'page' => $page,
            'title' => self::PAGES[$page],
            'settings' => $settings,
            'preview' => $preview,
            'pages' => self::PAGES,
        ]);
    }
}
