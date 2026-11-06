# Orden general de platos

El formulario administrativo propone el siguiente orden libre. Crear o mover un plato a un orden ocupado devuelve un error del campo sin modificar el plato ni sus adicionales. El orden es global, incluso entre categorías distintas; los platos no disponibles también lo reservan. Los eliminados mediante borrado lógico no lo reservan.

No se reorganizan los datos existentes. Si ya hay órdenes repetidos se permite editar otros datos manteniendo ese orden, o mover expresamente el plato a una posición libre. El catálogo conserva su prioridad: destacados, orden numérico y nombre.

ProductOrderService serializa sus escrituras mediante el bloqueo de la fila de configuración y guarda plato y adicionales en una transacción. No hay migraciones ni restricción única en base de datos: escrituras directas o importaciones que omitan el servicio pueden introducir duplicados. La concurrencia real de PostgreSQL requiere validación aparte; SQLite no demuestra el comportamiento del bloqueo.

La carga de imágenes mantiene su funcionamiento previo y ocurre antes del guardado del plato. Un error de orden no asocia la nueva imagen al plato; puede dejar un recurso subido sin asociación.

## Validación

Se añaden 10 pruebas en ProductOrderTest: sugerencia global, límite entero, conflictos entre categorías y con platos no disponibles, creación y slug, adicionales, empates históricos, borrado lógico, actualización HTTP y prioridad de destacados.

No se pudieron ejecutar PHP ni compilación en el entorno de edición porque el proceso de comandos no pudo iniciarse. Ejecutar en el servidor:

```bash
php artisan test --filter=ProductOrderTest
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Revisión manual: abrir dos formularios de alta, intentar usar el mismo orden; solo el primero debe guardarse. Editar un plato existente y probar un orden ocupado; verificar mensaje y conservación de datos/adicionales. Comprobar catálogo general y filtro por categoría.
