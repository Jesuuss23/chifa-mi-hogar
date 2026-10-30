# Tema de adicionales y sus categorías

Se adaptan los cuatro listados/formularios de adicionales y categorías de
adicionales al tema crema/terracota, reutilizando admin-catalog-surface.

- Tabla de adicionales desplazable en móvil, caption y encabezados con scope.
- Tarjetas, controles y paginaciones coherentes con el resto del catálogo.
- Etiquetas asociadas a campos con IDs únicos.
- Verde para disponibles/activos y destructivo para errores/no disponibles.
- Un solo mensaje de éxito desde layouts.admin.
- Acciones inferiores adaptables y sin padding duplicado del contenedor.

No modifica controladores, precios, relaciones con platos, condiciones de
disponibilidad, imágenes, validaciones ni confirmaciones de eliminación.

## Validación

```bash
php artisan test
npm ci
npm run build
php artisan view:clear
```

Revisión estática: mismas rutas, campos, métodos, values, multipart, accept,
CSRF, checked/selected y condiciones. Etiquetas asociadas a IDs únicos.
No se agregan pruebas que reflejen clases CSS.
PHP, build y revisión visual pendientes por el bloqueo conocido del entorno.
No requiere migrate.

Revisar ambos listados y formularios en móvil/escritorio, imágenes actuales,
selección de categoría, disponibilidad, precios, errores y paginación.
