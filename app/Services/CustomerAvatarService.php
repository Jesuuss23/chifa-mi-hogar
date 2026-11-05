<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use RuntimeException;
use Throwable;

class CustomerAvatarService
{
    public function store(UploadedFile $file, int $userId): string
    {
        $manager = new ImageManager(new Driver());
        $image = $manager->decodePath($file->getRealPath());
        $image->scaleDown(width: 256, height: 256);
        $encoded = (string) $image->encode(new WebpEncoder(quality: 80));
        $path = 'customer-avatars/'.$userId.'/'.Str::uuid().'.webp';

        try {
            if (! Storage::disk('local')->put($path, $encoded)) {
                throw new RuntimeException('Avatar storage failed.');
            }
        } catch (Throwable $exception) {
            $this->discard($path, $userId);
            throw $exception;
        }

        return $path;
    }

    public function ownsPath(?string $path, int $userId): bool
    {
        return is_string($path) && preg_match(
            '#^customer-avatars/'.preg_quote((string) $userId, '#').'/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.webp$#D',
            $path
        ) === 1;
    }

    public function discard(?string $path, int $userId): void
    {
        if (! $this->ownsPath($path, $userId)) {
            return;
        }
        try {
            if (! Storage::disk('local')->delete($path)) {
                Log::warning('Customer avatar cleanup failed.');
            }
        } catch (Throwable) {
            Log::warning('Customer avatar cleanup failed.');
        }
    }
}
