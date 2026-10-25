<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SocialLink;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SocialLinkController extends Controller
{
    private const PLATFORMS = [
        'facebook' => 'Facebook',
        'instagram' => 'Instagram',
        'tiktok' => 'TikTok',
        'youtube' => 'YouTube',
        'whatsapp' => 'WhatsApp',
    ];

    public function index()
    {
        $links = SocialLink::query()
            ->orderBy('sort_order')
            ->get()
            ->keyBy('platform');

        return view('admin.social-links.index', [
            'links' => $links,
            'platforms' => self::PLATFORMS,
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'links' => ['required', 'array'],
        ]);

        foreach (self::PLATFORMS as $platform => $label) {
            $input = $data['links'][$platform] ?? [];

            $url = trim((string) ($input['url'] ?? ''));
            $active = ($input['is_active'] ?? null) == '1';

            if ($url !== '') {
                $validator = Validator::make(
                    ['url' => $url],
                    ['url' => ['required', 'url:http,https', 'max:500']]
                );

                $validator->validate();

                $host = strtolower((string) parse_url($url, PHP_URL_HOST));

                $allowedHosts = match ($platform) {
                    'facebook' => ['facebook.com', 'www.facebook.com', 'fb.com', 'www.fb.com'],
                    'instagram' => ['instagram.com', 'www.instagram.com'],
                    'tiktok' => ['tiktok.com', 'www.tiktok.com'],
                    'youtube' => ['youtube.com', 'www.youtube.com', 'youtu.be'],
                    'whatsapp' => ['wa.me', 'api.whatsapp.com'],
                };

                if (!in_array($host, $allowedHosts, true)) {
                    return back()->withErrors([
                        'links' => "El enlace de {$label} no corresponde a esa plataforma.",
                    ])->withInput();
                }
            }

            SocialLink::updateOrCreate(
                ['platform' => $platform],
                [
                    'url' => $url !== '' ? $url : null,
                    'is_active' => $active && $url !== '',
                    'sort_order' => array_search(
                        $platform,
                        array_keys(self::PLATFORMS),
                        true
                    ),
                ]
            );
        }

        return back()->with('success', 'Redes sociales actualizadas.');
    }
}