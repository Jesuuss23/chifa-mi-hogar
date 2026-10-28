<?php

namespace App\Services;

class StorefrontStructuredDataService
{
    public function forPage(string $view, array $seo, string $base): array
    {
        $home = $base.route('storefront.index', [], false);
        $site = ['@id' => $home.'#website'];
        $graph = [[
            '@type' => 'WebSite',
            '@id' => $site['@id'],
            'url' => $home,
            'name' => 'Chifa Mi Hogar',
            'inLanguage' => 'es-PE',
        ], [
            '@type' => in_array($view, ['storefront.index', 'storefront.combos.index'], true)
                ? 'CollectionPage' : 'WebPage',
            '@id' => $seo['canonical'].'#webpage',
            'url' => $seo['canonical'],
            'name' => $seo['title'],
            'description' => $seo['description'],
            'isPartOf' => $site,
            'inLanguage' => 'es-PE',
        ]];

        if ($seo['canonical'] !== $home) {
            $items = [['@type' => 'ListItem', 'position' => 1, 'name' => 'Carta', 'item' => $home]];
            if ($view === 'storefront.combos.show') {
                $items[] = ['@type' => 'ListItem', 'position' => 2, 'name' => 'Combos',
                    'item' => $base.route('combos.index', [], false)];
            }
            $items[] = ['@type' => 'ListItem', 'position' => count($items) + 1,
                'name' => $seo['title'], 'item' => $seo['canonical']];
            $breadcrumbId = $seo['canonical'].'#breadcrumb';
            $graph[1]['breadcrumb'] = ['@id' => $breadcrumbId];
            $graph[] = ['@type' => 'BreadcrumbList', '@id' => $breadcrumbId, 'itemListElement' => $items];
        }

        return ['@context' => 'https://schema.org', '@graph' => $graph];
    }
}
