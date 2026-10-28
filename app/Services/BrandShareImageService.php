<?php

namespace App\Services;

use App\Models\StoreSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class BrandShareImageService
{
    public function source(StoreSetting $settings): ?string
    {
        $path = $settings->logo?->detail_path;
        $file = $path && Storage::disk('public')->exists($path)
            ? Storage::disk('public')->path($path)
            : public_path('images/brand/logo.jpeg');

        if (!is_file($file) || filesize($file) > 5 * 1024 * 1024) {
            return null;
        }
        $info = @getimagesize($file);
        if (!$info || $info[0] < 1 || $info[1] < 1
            || $info[0] > 4096 || $info[1] > 4096
            || $info[0] * $info[1] > 8000000
            || !in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)) {
            return null;
        }

        return $file;
    }

    public function version(?string $source): string
    {
        return substr(hash('sha256', 'brand-card-v1|'.($source ? hash_file('sha256', $source) : 'default')), 0, 24);
    }

    public function jpeg(StoreSetting $settings): string
    {
        $source = $this->source($settings);
        $key = 'brand-share:'.$this->version($source);

        return Cache::remember($key, now()->addDay(), fn () => $this->render($source));
    }

    private function render(?string $source): string
    {
        $canvas = imagecreatetruecolor(1200, 630);
        if (!$canvas) {
            throw new RuntimeException('No se pudo crear la imagen de marca.');
        }
        $background = imagecolorallocate($canvas, 246, 237, 199);
        $foreground = imagecolorallocate($canvas, 160, 69, 38);
        imagefill($canvas, 0, 0, $background);
        imagefilledrectangle($canvas, 0, 0, 1199, 15, $foreground);
        imagefilledrectangle($canvas, 0, 614, 1199, 629, $foreground);

        $logo = $source ? @imagecreatefromstring((string) @file_get_contents($source)) : false;
        if ($logo !== false) {
            $scale = min(880 / imagesx($logo), 350 / imagesy($logo), 2);
            $width = max(1, (int) round(imagesx($logo) * $scale));
            $height = max(1, (int) round(imagesy($logo) * $scale));
            imagecopyresampled($canvas, $logo, (int) ((1200 - $width) / 2),
                65 + (int) ((350 - $height) / 2), 0, 0,
                $width, $height, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
            $this->text($canvas, 'CHIFA MI HOGAR', 3, 435);
        } else {
            $this->text($canvas, 'CHIFA MI HOGAR', 6, 205);
            $this->text($canvas, 'DARK KITCHEN', 3, 330);
        }
        $this->text($canvas, 'HOY SE ANTOJA CHIFA. PIDE TU FAVORITO.', 2, 535);

        ob_start();
        try {
            if (!imagejpeg($canvas, null, 80)) {
                throw new RuntimeException('No se pudo codificar la imagen de marca.');
            }
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
            imagedestroy($canvas);
        }
    }

    private function text(\GdImage $canvas, string $text, int $scale, int $y): void
    {
        $width = imagefontwidth(5) * strlen($text);
        $height = imagefontheight(5);
        $label = imagecreatetruecolor($width, $height);
        $background = imagecolorallocate($label, 246, 237, 199);
        $foreground = imagecolorallocate($label, 160, 69, 38);
        imagefill($label, 0, 0, $background);
        imagestring($label, 5, 0, 0, $text, $foreground);
        imagecopyresampled($canvas, $label, (int) ((1200 - $width * $scale) / 2),
            $y, 0, 0, $width * $scale, $height * $scale, $width, $height);
        imagedestroy($label);
    }
}
