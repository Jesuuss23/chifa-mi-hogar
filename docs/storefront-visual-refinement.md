# Revisión visual de la portada

Combos se retira de las variantes de navegación superior (escritorio y móvil) y se coloca inmediatamente después de Todos en las categorías. Es un enlace al carrusel existente #combos, no un filtro de platos. Se muestra si existen combos públicos. Las categorías y la paginación conservan sus consultas y URLs; el pie mantiene sus enlaces.

Portada más compacta con CTA, tipografía jerarquizada y una fotografía real del primer plato de la página que tenga referencia de imagen. CatalogImageService comprueba el archivo; si esa referencia no resuelve, la portada queda sin foto. No se crea una selección aleatoria ni una consulta de catálogo nueva. En páginas filtradas/paginadas la fotografía corresponde a esa selección, con precio y enlace del mismo producto.

Tarjetas de platos con contenedor 4:3 para reservar espacio incluso si falta foto, esquinas suaves y botones redondeados. Carrusel con fondo suave y títulos más contenidos. Categorías desplazables y estado seleccionado accesible. Sin cambios en precios, disponibilidad, personalización, carrito, APIs ni administración.

No se incorpora Manrope ni selector de temas todavía; se conservan como siguientes pasos. No se añaden animaciones ni recursos externos.

## Validación

Revisión estática de enlaces, anclas, selección de datos y alcance CSS. No se pudo cargar el dominio desde la herramienta ni iniciar la terminal por el bloqueo del sandbox; build, pruebas y revisión visual pendientes.

```bash
php artisan test --filter=StorefrontComboCarouselTest
php artisan test --filter=StorefrontSeoTest
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Revisar móvil/escritorio: Todos → Combos → categoría → página 2; personalizar combo y plato; carrito; catálogo vacío y fotos ausentes. El carrusel mantiene su posición antes del catálogo. No requiere migraciones.
