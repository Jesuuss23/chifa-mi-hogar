<?php

namespace App\Services;

use App\Models\StoreSetting;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class StorefrontSeoService
{
    public function __construct(private readonly BrandShareImageService $images) {}

    public function forView(string $view, array $data): array
    {
        $settings = StoreSetting::current()->load('logo');
        $base = $this->baseUrl($settings);
        $title = 'Chifa Mi Hogar | Dark Kitchen de chifa en SJL';
        $description = 'Hoy se antoja chifa. Descubre nuestros platos y combos y pide tu favorito a domicilio en San Juan de Lurigancho, Lima.';
        $image = $base.route('brand.share-image', [], false)
            .'?v='.$this->images->version($this->images->source($settings));
        $imageAlt = 'Chifa Mi Hogar, dark kitchen de chifa a domicilio';
        $imageType = 'image/jpeg';
        $imageWidth = 1200;
        $imageHeight = 630;
        $query = [];
        $media = null;

        switch ($view) {
            case 'storefront.index':
                $path = route('storefront.index', [], false);
                if ($category = ($data['selectedCategory'] ?? null)) {
                    $query['categoria'] = $category->slug;
                    $title = $category->name.' | Chifa Mi Hogar';
                    $description = 'Descubre '.$category->name.' en Chifa Mi Hogar. Elige tu favorito y pídelo por delivery en SJL.';
                }
                $page = $data['products']->currentPage();
                break;
            case 'storefront.show':
                $product = $data['product'];
                $path = route('storefront.show', $product, false);
                $title = $product->name.' | Chifa Mi Hogar';
                $description = $product->description ?: 'Pide '.$product->name.' por delivery en Chifa Mi Hogar, San Juan de Lurigancho.';
                $media = $product->mediaAsset;
                $imageAlt = $product->name;
                $page = 1;
                break;
            case 'storefront.combos.index':
                $path = route('combos.index', [], false);
                $title = 'Combos de chifa a domicilio en SJL | Chifa Mi Hogar';
                $description = 'Encuentra tu combo favorito de chifa, personalízalo y pídelo por delivery en San Juan de Lurigancho.';
                $page = $data['combos']->currentPage();
                break;
            case 'storefront.combos.show':
                $combo = $data['combo'];
                $path = route('combos.show', $combo, false);
                $title = $combo->name.' | Chifa Mi Hogar';
                $description = $combo->description ?: 'Disfruta '.$combo->name.'. Personaliza tu combo y pídelo por delivery en SJL.';
                $media = $combo->mediaAsset;
                $imageAlt = $combo->name;
                $page = 1;
                break;
            default:
                throw new \InvalidArgumentException('Vista no pública para metadatos de catálogo.');
        }

        if ($page > 1) {
            $query['page'] = $page;
            $title .= ' | Página '.$page;
        }
        if ($media && $media->detail_path && Storage::disk('public')->exists($media->detail_path)) {
            $image = $base.'/storage/'.implode('/', array_map('rawurlencode', explode('/', $media->detail_path)));
            $imageType = 'image/webp';
            // Las dimensiones del original no son las de la variante.
            $imageWidth = $imageHeight = null;
        } else {
            $imageAlt = 'Chifa Mi Hogar, dark kitchen de chifa a domicilio';
        }

        return [
            'title' => $this->plain($title, 140),
            'description' => $this->plain($description, 160) ?: 'Descubre la carta de Chifa Mi Hogar y pide tu favorito por delivery en SJL.',
            'canonical' => $base.$path.($query ? '?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986) : ''),
            'image' => $image,
            'image_alt' => $this->plain($imageAlt, 140),
            'image_type' => $imageType,
            'image_width' => $imageWidth,
            'image_height' => $imageHeight,
        ];
    }

    private function plain(string $value, int $length): string
    {
        $value = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return Str::limit(trim(preg_replace('/\\s+/u', ' ', $value) ?? ''), $length);
    }

    private function baseUrl(StoreSetting $settings): string
    {
        $parts = parse_url((string) $settings->website_url);
        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host'])) {
            return 'https://chifamihogar.com';
        }

        // No usar Host ni parámetros de la petición como identidad canónica.
        return 'https://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '');
    }
}
