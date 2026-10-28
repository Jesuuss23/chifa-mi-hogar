<?php

namespace App\Http\Controllers;

use App\Models\Combo;
use App\Models\Product;
use App\Models\StoreSetting;
use App\Services\StorefrontSeoService;
use Illuminate\Http\Response;

class SitemapController extends Controller
{
    public function __invoke(StorefrontSeoService $seo): Response
    {
        $base = $seo->baseUrl(StoreSetting::current());
        $urls = [
            $base.route('storefront.index', [], false),
            $base.route('combos.index', [], false),
        ];

        $products = Product::query()->select(['id', 'slug'])
            ->where('is_available', true)
            ->whereHas('category', fn ($query) => $query->where('is_active', true));
        $combos = Combo::query()->select(['id', 'slug'])
            ->where('is_available', true)->where('show_in_menu', true);

        foreach ([$products, $combos] as $query) {
            foreach ($query->lazyById(500) as $item) {
                $route = $item instanceof Product ? 'storefront.show' : 'combos.show';
                $urls[] = $base.route($route, $item, false);
                // No entregar un sitemap truncado o fuera del límite del protocolo.
                abort_if(count($urls) > 50000, 503, 'El catálogo requiere un índice de sitemaps.');
            }
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach (array_unique($urls) as $url) {
            $xml .= '  <url><loc>'.htmlspecialchars($url, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</loc></url>'."\n";
        }
        $xml .= '</urlset>';
        abort_if(strlen($xml) > 50 * 1024 * 1024, 503, 'El catálogo requiere un índice de sitemaps.');

        return response($xml, 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
