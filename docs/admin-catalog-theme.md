# Tema del catálogo administrativo

Se adaptan seis vistas: listado y formulario de categorías, platos y
recomendaciones. Se reutilizan los tokens crema/terracota del layout, con
tarjetas rectas, botones coherentes y paginación temática existente.

Los estados disponibles/activos mantienen verde; agotados y errores usan
destructivo. Se conservan las imágenes, precios y selección de adicionales.
Los campos principales tienen etiquetas asociadas mediante for/id.
Las acciones inferiores pueden envolver a otra línea en pantallas pequeñas.
Se retira el padding duplicado del contenedor interior, porque layouts.admin
ya proporciona espacio alrededor del contenido.

Las confirmaciones de éxito se muestran una sola vez desde layouts.admin.
No se modifican controladores, validaciones, nombres de campos, formularios
multipart, CSRF, métodos PUT/DELETE ni confirmaciones al eliminar.
Los estilos de campos se limitan a .admin-catalog-surface.

## Validación

```bash
php artisan test --filter=CatalogSlugTest
php artisan test --filter=RecommendationSlugTest
php artisan test --filter=RecommendationImageEditingTest
php artisan test
npm ci
npm run build
php artisan view:clear
```

Comparación estática de rutas, campos, métodos, condiciones, selección de extras,
carga de imágenes y confirmaciones de eliminación. Etiquetas asociadas a IDs únicos.
No se agregan pruebas que solo reflejen clases CSS.

PHP, build y revisión visual real pendientes por el bloqueo conocido de ejecución.
No requiere migrate.

Revisar móvil/escritorio: los tres listados y sus paginaciones, crear/editar con
y sin foto nueva, selección de adicionales, estados, errores y mensaje único.
Extras, combos administrativos, ajustes y dashboard mantienen por ahora sus
vistas interiores anteriores; se abordarán por módulos.
