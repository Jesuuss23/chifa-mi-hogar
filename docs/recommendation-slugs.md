# Identificadores de recomendaciones

El índice existente exige que (product_id, slug) sea único, incluso para filas
eliminadas mediante soft delete. El controlador generaba siempre Str::slug(name):
nombres iguales o equivalentes podían provocar un error de base de datos al guardar.
Además, renombrar una recomendación podía colisionar con otra.

Se reutiliza CatalogSlugService:
- Genera sufijos cuando el slug está reservado dentro del mismo plato.
- Permite el mismo slug en platos distintos, conforme al índice existente.
- Incluye registros eliminados al buscar colisiones.
- Mantiene el límite de 180 caracteres y usa recomendacion si el nombre no genera slug.
- Conserva los reintentos con transacción/savepoint del servicio.
- Editar el nombre mantiene el identificador existente.

El plato sigue viniendo de la ruta y los extras mantienen su validación y sync.
No se cambia ningún registro histórico ni se agregan migraciones.

## Validación

```bash
php artisan test --filter=RecommendationSlugTest
php artisan test --filter=CatalogSlugTest
php artisan test
```

Siete casos nuevos: nombres equivalentes, alcance por plato, borrado lógico,
renombrado/identidad no controlable por el formulario, Unicode/longitud,
colisión entre consulta e inserción y rechazo al editar desde otro plato.

La colisión se reproduce de forma determinista mediante un listener, no con
procesos concurrentes. Revisión estática realizada; PHPUnit no ejecutado en
el entorno del asistente por el bloqueo conocido de ejecución.

No requiere migrate ni npm build.
