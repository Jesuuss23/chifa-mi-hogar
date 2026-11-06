<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Models\Combo;
use App\Models\Extra;
use App\Models\ExtraCategory;
use App\Models\Product;
use App\Models\ProductRecommendation;
use App\Services\LegacyCatalogImageService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class MigrateLegacyCatalogImages extends Command
{
    protected $signature = 'catalog:migrate-images
        {--type=products : products, categories, extras, extra-categories, recommendations o combos}
        {--limit=50 : Máximo de registros a revisar (1-500)}
        {--after-id=0 : Revisar únicamente IDs mayores que este valor}
        {--apply : Convertir; sin esta opción solo se revisan los archivos}';

    protected $description = 'Revisar o convertir imágenes históricas del catálogo a WebP sin borrar originales';

    public function handle(LegacyCatalogImageService $images): int
    {
        $models = [
            'products' => Product::class, 'categories' => Category::class,
            'extras' => Extra::class, 'extra-categories' => ExtraCategory::class,
            'recommendations' => ProductRecommendation::class, 'combos' => Combo::class,
        ];
        $type = $this->option('type');
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT);
        $after = filter_var($this->option('after-id'), FILTER_VALIDATE_INT);
        if (! isset($models[$type]) || $limit === false || $limit < 1 || $limit > 500
            || $after === false || $after < 0) {
            $this->error('Opciones inválidas. Revisa --type, --limit (1-500) y --after-id (entero no negativo).');
            return self::FAILURE;
        }
        $class = $models[$type];
        $model = new $class;
        if (! Schema::hasColumn($model->getTable(), 'image_path')) {
            $this->info('Esta tabla no tiene image_path histórico; no hay nada que convertir.');
            return self::SUCCESS;
        }
        $items = $model->newQuery()->whereNull('media_asset_id')
            ->whereNotNull('image_path')->where('image_path', '!=', '')
            ->where('id', '>', $after)->orderBy('id')->limit($limit)->get();

        $apply = (bool) $this->option('apply');
        $this->info($apply ? 'Modo APLICAR: conversión por registro.' : 'Modo REVISIÓN: no se modifican imágenes ni registros.');
        $errors = 0;
        foreach ($items as $item) {
            try {
                if ($apply) {
                    $status = $images->migrate($item) ? 'CONVERTIDO' : 'OMITIDO: registro cambiado o ya convertido';
                } else {
                    $images->source($item);
                    $status = 'LISTO';
                }
                $this->line($type.' #'.$item->getKey().': '.$status);
            } catch (Throwable $exception) {
                $errors++;
                report($exception);
                $this->warn($type.' #'.$item->getKey().': ERROR; consulta el registro de Laravel para el detalle.');
            }
        }
        $this->info('Revisados: '.$items->count().'; errores: '.$errors.'.');
        if ($items->isNotEmpty()) {
            $this->line('Último ID revisado: '.$items->last()->getKey().'. Usa --after-id para avanzar; los errores anteriores quedan pendientes.');
        }

        return $errors > 0 ? self::FAILURE : self::SUCCESS;
    }
}
