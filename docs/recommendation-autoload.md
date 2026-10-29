# Autoload de recomendaciones

## Corrección

Se elimina app/Http/Controllers/Admin/ProductRecommendation.php: declaraba App\\Models\\ProductRecommendation y duplicaba el modelo real.
Se conserva sin cambios app/Models/ProductRecommendation.php, incluidas sus relaciones, fillable, casts, soft deletes y tabla explícita.
El controlador real sigue siendo ProductRecommendationController.php y ya importa el modelo correcto.

## Verificación

Se revisaron estáticamente los 81 archivos PHP de app en la base de esta rama. Se encontraron 81 declaraciones y una ubicación incompatible con PSR-4: la copia eliminada.
No se encontraron referencias directas al archivo eliminado en esos archivos.
Esta inspección de declaraciones no sustituye al análisis de Composer.

Se añade el comando opcional:
```bash
composer check:autoload
```
Regenera el autoload optimizado y solicita a Composer fallar ante incompatibilidades PSR o declaraciones ambiguas. Conserva el script post-autoload-dump de Laravel; no ejecuta composer update ni cambia versiones.
No se integra automáticamente en install/test para mantener el flujo existente.

## Probar

```bash
git fetch origin
git switch fix/recommendation-model-autoload
composer check:autoload
php artisan test
```

No requiere migraciones ni compilación de assets.
Comprobar listado/edición de recomendaciones en administración y selección de una recomendación en la ficha de un plato.
No se añaden pruebas que solo comprueben la ausencia de un archivo: se utiliza el verificador de autoload de Composer y la suite existente.

Composer y PHPUnit pendientes de ejecución: el entorno local no pudo iniciar procesos por bloqueo de permisos del sandbox.

## Siguiente pendiente separado

Las migraciones create_order_items_table y create_orders_table comparten el prefijo 2026_10_24_085832.
El orden alfabético coloca order_items antes de orders, aunque su clave foránea referencia orders.
Esta rama no cambia los archivos ni nombres de migraciones históricas. La corrección debe cubrir instalación nueva y reversión sin romper el historial de instalaciones existentes.

Referencia: https://getcomposer.org/doc/03-cli.md#dump-autoload-dumpautoload
