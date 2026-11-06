# Conversión histórica de imágenes del catálogo

La herramienta usa ImageService y la librería de imágenes ya instalada. Genera WebP con calidad 85 en tamaños de hasta 240, 640 y 1200 px de ancho, sin ampliar imágenes pequeñas. Guarda una copia privada del original y conserva el archivo público histórico y su image_path.

## Revisar primero

```bash
php artisan catalog:migrate-images --type=products --limit=50
```

Sin --apply, revisa rutas, existencia, tamaño y cabecera de imagen; no convierte ni modifica imágenes o registros. LISTO indica que superó esa revisión: la decodificación completa aún puede fallar al convertir. Los errores se identifican por tipo e ID y se registran en el log de Laravel. El comando devuelve código 1 si algún registro falla, pero continúa revisando el resto.

Tipos: products, categories, extras, extra-categories, recommendations, combos. Se procesa un solo tipo por ejecución; products es el predeterminado. Si una tabla no tiene columna histórica image_path, se informa y no se altera el esquema.

## Aplicar por lotes

Antes de la primera aplicación, disponer de una copia recuperable de base de datos y almacenamiento; probar primero en staging. No se ejecuta automáticamente al desplegar, migrar ni por scheduler.

```bash
php artisan catalog:migrate-images --type=products --limit=20 --apply
```

Se seleccionan registros no eliminados, sin media_asset_id y con image_path no vacío. Incluye elementos temporalmente inactivos/no disponibles. No repara ni reemplaza imágenes ya asociadas. Repetir el comando omite las conversiones completadas.

El límite es de 1 a 500 registros; predeterminado 50. Se informa el último ID examinado. Para avanzar más allá de registros con errores, usar --after-id=ID con el último ID mostrado. Los errores anteriores siguen pendientes: corregir la fuente y volver a revisar desde cero. Repetir este procedimiento para cada tipo necesario.

## Validaciones y recuperación

Solo lee archivos del disco public local: rutas relativas y prefijos históricos storage/ o /storage/. Rechaza URLs remotas, navegación fuera del directorio y enlaces simbólicos que salgan de la raíz pública. No lee originales privados como fuente.

Límites: JPEG/PNG/WebP, hasta 5 MB, 4096 px por lado y 8 millones de píxeles. Para imágenes mayores, usar una copia reducida mediante la carga habitual del administrador. Los límites evitan decodificar imágenes históricas excesivamente grandes.

Cada registro se bloquea y se vuelve a comprobar antes de convertir. Se omite si cambió su ruta, fue eliminado o ya tiene media_asset_id. La creación de MediaAsset y su asociación usan una transacción por registro; solo se modifica media_asset_id (y updated_at). Si falla la asociación, se revierte la base de datos y se intenta eliminar únicamente los archivos generados en ese intento. ImageService ahora comprueba también los resultados false del almacenamiento para no registrar conversiones incompletas.

El archivo histórico nunca se borra. Un cierre forzado del proceso, un fallo de conexión al confirmar la transacción o un fallo de limpieza del disco pueden dejar archivos sin asociación; no se incluye borrado automático de huérfanos. Revisar logs y copias antes de intervenir. No ejecutar cambios manuales de archivos mientras se convierte. La prueba de concurrencia real en PostgreSQL queda pendiente.

## Pruebas de la entrega

```bash
php artisan test --filter=LegacyCatalogImageMigrationTest
php artisan test --filter=ImageStorageFailureTest
php artisan test --filter=CatalogImageCompatibilityTest
php artisan test
```

Las pruebas cubren revisión sin escrituras, conversión real a WebP, repetición, conservación del original, registros con error seguidos de otros válidos, rutas no admitidas, límites, selección por tipo/lote, cambios entre lectura y conversión, rollback de asociación y fallos de almacenamiento.

No ejecutadas en el entorno de edición: el sistema impidió iniciar la terminal por un error de aprovisionamiento del sandbox. Requieren PHP/GD con soporte WebP, ya utilizado por las cargas habituales. No hay dependencias ni migraciones nuevas.
