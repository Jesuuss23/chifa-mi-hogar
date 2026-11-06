<?php

namespace App\Services;

use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class LegacyCatalogImageService
{
    public function source(Model $item): string
    {
        if (config('filesystems.disks.public.driver') !== 'local') {
            throw new RuntimeException('La conversión requiere el disco público local.');
        }
        $path = app(CatalogImageService::class)->publicPath($item->getAttribute('image_path'));
        if ($path === null) {
            throw new RuntimeException('Ruta histórica no válida.');
        }

        $disk = Storage::disk('public');
        $root = realpath($disk->path(''));
        $file = realpath($disk->path($path));
        if ($root === false || $file === false
            || ! str_starts_with($file, rtrim($root, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR)
            || ! is_file($file) || ! is_readable($file)) {
            throw new RuntimeException('Archivo ausente o fuera del disco público.');
        }
        $size = filesize($file);
        if ($size === false || $size < 1 || $size > 5 * 1024 * 1024) {
            throw new RuntimeException('El archivo debe pesar entre 1 byte y 5 MB.');
        }
        $info = @getimagesize($file);
        if (! $info || ! in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP], true)
            || $info[0] < 1 || $info[1] < 1 || $info[0] > 4096 || $info[1] > 4096
            || $info[0] * $info[1] > 8000000) {
            throw new RuntimeException('Imagen no admitida: JPEG, PNG o WebP, máximo 4096 px por lado y 8 megapíxeles.');
        }

        return $file;
    }

    public function migrate(Model $item): bool
    {
        $created = null;
        try {
            // One row at a time; no transaction retry around filesystem writes.
            return DB::transaction(function () use ($item, &$created) {
                $current = $item->newQuery()->whereKey($item->getKey())->lockForUpdate()->first();
                if (! $current || $current->getAttribute('media_asset_id') !== null
                    || $current->getAttribute('image_path') !== $item->getAttribute('image_path')) {
                    return false;
                }

                $source = $this->source($current);
                $created = app(ImageService::class)->upload(
                    new UploadedFile($source, basename($source), null, null, true)
                );
                $current->setAttribute('media_asset_id', $created->getKey());
                if (! $current->saveOrFail()) {
                    throw new RuntimeException('No se pudo asociar la imagen convertida.');
                }

                return true;
            });
        } catch (Throwable $exception) {
            // The DB transaction rolled back; remove only this attempt's generated files.
            if ($created instanceof MediaAsset) {
                Storage::disk('public')->delete([
                    $created->thumbnail_path, $created->card_path, $created->detail_path,
                ]);
                Storage::disk('local')->delete($created->original_path);
            }
            throw $exception;
        }
    }
}
