<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class CatalogImageService
{
    public function url(Model $item, string $variant = 'card'): ?string
    {
        $media = $item->mediaAsset;
        $preferred = match ($variant) {
            'thumbnail' => 'thumbnail_path',
            'detail' => 'detail_path',
            default => 'card_path',
        };

        // Prefer another optimized size before falling back to the historical image.
        $candidates = [
            $media?->getAttribute($preferred),
            $media?->card_path,
            $media?->detail_path,
            $media?->thumbnail_path,
            $item->getAttribute('image_path'),
        ];
        $disk = Storage::disk('public');
        $checked = [];

        foreach ($candidates as $candidate) {
            $path = $this->publicPath($candidate);
            if ($path === null || isset($checked[$path])) {
                continue;
            }
            $checked[$path] = true;
            if ($disk->exists($path)) {
                return $disk->url($path);
            }
        }

        return null;
    }

    private function publicPath(mixed $value): ?string
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        // Only public-disk paths. Never request remote URLs or private originals.
        if (preg_match('/[\x00-\x1F\x7F:%?#]/', $value)) {
            return null;
        }
        $path = str_replace('\\', '/', $value);
        if (str_starts_with($path, '//')) {
            return null;
        }
        if (str_starts_with($path, '/storage/')) {
            $path = substr($path, strlen('/storage/'));
        } elseif (str_starts_with($path, 'storage/')) {
            $path = substr($path, strlen('storage/'));
        }
        if ($path === '' || str_starts_with($path, '/')) {
            return null;
        }
        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        return $path;
    }
}
