# Combos dentro de la carta

Los combos aparecen después de la presentación y antes de los platos, dentro de
la misma carta. El menú público (escritorio, móvil y pie) apunta a #combos:
en la carta se desplaza a la sección sin recargar; en otras páginas vuelve a
la carta. El detalle del combo también regresa a esa sección.

## Presentación y flujo

- Tarjetas crema/terracota con imagen, nombre, descripción, hasta tres componentes,
  precio base y botón Personalizar combo.
- La personalización conserva la página y el formulario existentes; no se abre
  una pestaña nueva ni se cambia el carrito, cálculo de precios o checkout.
- Desplazamiento horizontal nativo, sin autoplay ni librerías adicionales.
- En móvil asoma la siguiente tarjeta; desde 1024 px caben tres.
- Flechas solo cuando hay desbordamiento, deshabilitadas en los extremos.
- Foco visible y acceso por teclado. Se respeta prefers-reduced-motion.
- Sin JavaScript, las tarjetas y enlaces siguen accesibles con scroll nativo.
- Las imágenes mantienen su proporción; se usa la variante card o image_path
  anterior, con marcador de marca si no hay fotografía.
- Solo aparecen is_available + show_in_menu; soft deletes quedan excluidos.
- Orden por sort_order, nombre e ID. Se cargan todos los combos publicados,
  con relaciones precargadas e imágenes posteriores de carga diferida.
- Los filtros/paginación de platos no filtran los combos.
- Sin combos se omite la sección visual y sus controles; el ancla queda antes
  de los platos para que los enlaces del menú sigan teniendo destino.
- /combos y sus metadatos siguen disponibles para enlaces anteriores.
  Las rutas públicas y los slugs no cambian.

## Comprobación

```bash
php artisan test --filter=StorefrontComboCarouselTest
php artisan test
node --test tests/Frontend/combo-carousel.test.cjs
npm ci
npm run build
php artisan view:clear
```

Siete pruebas PHP: orden y navegación, visibilidad, vacío, componentes/imagen
anterior, filtros/paginación independientes, detalle/carrito/ruta anterior y
escape de contenido. Seis pruebas JS: extremos, movimiento reducido, resize,
teclado, carga del DOM y páginas sin carrusel.

El asistente ejecutó los seis casos JS con adaptadores en V8; pasaron.
Esto no equivale a ejecutar Node, compilar Vite ni revisar un navegador real.
PHP/Node/build y revisión visual pendientes: el entorno no puede iniciar
procesos por helper_sandbox_lock_failed (acceso denegado).
No se requieren migraciones ni nuevas dependencias.

Revisión visual recomendada:
1. Publicar al menos cuatro combos y verificar el orden en la carta.
2. Probar a 360 px y en escritorio; deslizar y usar las flechas.
3. Probar Tab y flechas con foco en la lista; comprobar movimiento reducido.
4. Elegir Personalizar combo, añadir al carrito y continuar el checkout.
5. Filtrar una categoría y cambiar de página de platos.
6. Ocultar o desactivar combos; comprobar uno solo y ninguno.
