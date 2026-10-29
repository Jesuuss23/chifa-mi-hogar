<?php

namespace App\Services;

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductRecommendation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogSlugService
{
    public function create(string $modelClass, array $attributes): Model
    {
        [$limit, $fallback] = match ($modelClass) {
            Category::class => [120, 'categoria'],
            Product::class => [180, 'plato'],
            ProductRecommendation::class => [180, 'recomendacion'],
            default => throw new \InvalidArgumentException('Modelo de catálogo no admitido.'),
        };
        if ($modelClass === ProductRecommendation::class && empty($attributes['product_id'])) {
            throw new \InvalidArgumentException('La recomendación requiere un plato.');
        }
        $scope = $modelClass === ProductRecommendation::class
            ? ['product_id' => $attributes['product_id']]
            : [];

        $model = new $modelClass();
        $connection = $model->getConnection();
        $base = Str::slug($attributes['name']) ?: $fallback;

        // Incluir soft deletes: un enlace retirado no debe apuntar a otro artículo.
        $occupied = fn (string $slug) => $connection->table($model->getTable())
            ->where($scope)->where('slug', $slug)->exists();

        for ($attempt = 0; $attempt < 3; $attempt++) {
            $slug = Str::substr($base, 0, $limit);
            for ($number = 2; $occupied($slug); $number++) {
                $suffix = '-'.($number <= 100 ? $number : Str::lower((string) Str::ulid()));
                $slug = rtrim(Str::substr($base, 0, $limit - strlen($suffix)), '-').$suffix;
                if ($number > 100) {
                    break;
                }
            }

            try {
                // La transacción/savepoint se revierte antes de reintentar en PostgreSQL.
                return $connection->transaction(
                    fn () => $modelClass::create(array_replace($attributes, ['slug' => $slug]))
                );
            } catch (UniqueConstraintViolationException $exception) {
                if (! $occupied($slug)) {
                    throw $exception;
                }
                // Otro guardado pudo reservarlo después de nuestra consulta.
            }
        }

        throw ValidationException::withMessages([
            'name' => 'No se pudo reservar el enlace del catálogo. Intenta guardar nuevamente.',
        ]);
    }
}
