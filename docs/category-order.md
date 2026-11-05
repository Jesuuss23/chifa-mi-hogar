# Orden de categorías sin nuevos conflictos

Al crear una categoría se propone el siguiente número después del máximo usado. Si se alcanzó el máximo del entero, se busca un hueco. Al guardar una categoría nueva o cambiar su posición, se rechaza un número ocupado por otra categoría no eliminada, activa o inactiva.

No se reordenan datos existentes. Los empates históricos se conservan y permiten editar nombre, imagen y estado sin cambiar posición. Para resolverlos, asignar explícitamente un número libre. Una categoría eliminada lógicamente libera su posición; su slug sigue protegido por CatalogSlugService.

El formulario muestra el error junto a Orden, admite de 0 a 2147483647 y explica el comportamiento. Los números menores siguen primero. No añade arrastre, intercambio automático ni ordenación de platos: estos quedan para la siguiente entrega.

CategoryOrderService serializa los guardados administrativos mediante el registro de configuración existente, revalida la ocupación dentro de la transacción y conserva el generador de slugs. Así se evita que dos guardados que usan este servicio asignen simultáneamente el mismo número en PostgreSQL. No es una restricción de base de datos: scripts, restauraciones y escrituras directas deben respetar la misma política. La subida de imagen conserva el flujo existente.

## Pruebas

Ocho casos en CategoryOrderTest cubren sugerencias, conflicto con inactiva, alta con slug, empates históricos, reutilización tras eliminación, error HTTP sin mutación y formulario. No se ejecutó concurrencia real contra PostgreSQL.

Revisión estática realizada; PHPUnit/Blade/Vite pendientes porque Windows bloqueó el arranque de procesos.

```bash
php artisan test --filter=CategoryOrderTest
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Sin migraciones ni dependencias. Probar dos categorías con órdenes distintos, intentar repetir uno, editar otros datos conservando posición y desactivar una categoría para comprobar que su orden sigue reservado.
