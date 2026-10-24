<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Encoders\WebpEncoder;
use Throwable;

class ImageService
{
    private ImageManager $manager;

    public function __construct()
    {
        $this->manager = new ImageManager(new Driver());
    }

    public function upload(UploadedFile $file): MediaAsset
    {
        $id = (string) Str::uuid();
        $extension = strtolower($file->extension() ?: 'jpg');

        $originalPath = "originals/{$id}.{$extension}";

        $paths = [
            'thumbnail' => "catalog/thumbnails/{$id}.webp",
            'card' => "catalog/cards/{$id}.webp",
            'detail' => "catalog/details/{$id}.webp",
        ];

        $writtenPublicPaths = [];
        $originalSaved = false;

        try {
            // El original se guarda en un disco privado.
            Storage::disk('local')->putFileAs(
                'originals',
                $file,
                "{$id}.{$extension}"
            );

            $originalSaved = true;

            $image = $this->manager->decodePath($file->getRealPath());

            $width = $image->width();
            $height = $image->height();

            foreach ([
                'thumbnail' => 240,
                'card' => 640,
                'detail' => 1200,
            ] as $variant => $maxWidth) {

                $resized = $this->manager->decodePath(
                    $file->getRealPath()
                );

                $resized->scaleDown(width: $maxWidth);

                $encoded = $resized->encode(
                    new WebpEncoder(quality: 85)
                );

                Storage::disk('public')->put(
                    $paths[$variant],
                    (string) $encoded
                );

                $writtenPublicPaths[] = $paths[$variant];
            }

            return MediaAsset::create([
                'original_path' => $originalPath,
                'thumbnail_path' => $paths['thumbnail'],
                'card_path' => $paths['card'],
                'detail_path' => $paths['detail'],
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'original_size' => $file->getSize(),
                'width' => $width,
                'height' => $height,
            ]);

        } catch (Throwable $e) {
            Storage::disk('public')->delete($writtenPublicPaths);

            if ($originalSaved) {
                Storage::disk('local')->delete($originalPath);
            }

            throw $e;
        }
    }
}