# Banners promocionales y detalles visuales

## Publicación

Desde `main` actualizado, revisar la rama `feat/catalog-banners-and-visual-details`.

```bash
composer install
php artisan migrate
php artisan optimize:clear
php artisan test --filter='PromotionalBannerTest|CustomerViewSyntaxTest|ComboAvailabilityTest|StorefrontComboCarouselTest|CatalogImageCompatibilityTest|CustomerInterfaceTest'
node --test tests/Frontend/promotional-banners.test.cjs tests/Frontend/quantity-stepper.test.cjs tests/Frontend/combo-carousel.test.cjs tests/Frontend/admin-image-preview.test.cjs
npm ci
npm run build
php artisan view:cache
```

La migración crea `promotional_banners`; no modifica pedidos ni productos. Se debe migrar antes de servir las nuevas vistas. Requiere el enlace `public/storage` ya usado por las imágenes del catálogo.

## Uso

En el administrador: **Negocio → Banners**. Crear, editar, eliminar, ordenar y activar promociones. Cada banner apunta exclusivamente a un plato o combo elegido del catálogo; no acepta URLs externas.

- Imagen principal sugerida: **1600 × 600 px**.
- Imagen opcional para celular: **900 × 1100 px**, con texto legible.
- JPG, PNG o WebP, hasta 5 MB por archivo. Vista previa antes de guardar. La versión móvil puede retirarse sin reemplazar la principal.
- Se usa el servicio de imágenes existente: original privado y variantes WebP públicas. Las imágenes se muestran completas, sin recorte.
- Orden ascendente; en empates, primero el banner más antiguo.
- Se ocultan banners inactivos, sin archivo público o con destino eliminado/no disponible; también si la categoría del plato está inactiva o el combo no está publicado o no corresponde al día/fecha configurado.
- Sin banners publicables se conserva la portada anterior. La disponibilidad sigue comprobándose también al abrir el detalle y comprar.
- Carrusel manual con arrastre/deslizamiento, flechas, indicadores y teclado. Respeta movimiento reducido. No cambia promociones automáticamente mientras el cliente lee.
- Base neutra y color de marca, con acento dorado en el indicador activo. Las imágenes aportan el diseño de cada campaña; no se copian imágenes ni marcas de terceros.

Los detalles de combos muestran miniaturas de sus platos y adicionales. Si no existe el archivo, se muestra el marcador de imagen. Los componentes permanecen fijos: solo puede cambiarse la cantidad de combos (1–20), mediante −/+ y con actualización del total. También se usan −/+ para cantidades de componentes al editar combos en administración. Los importes y el orden siguen siendo campos de edición numérica.

## Verificación

Pruebas Laravel sobre SQLite y PHP 8.4: 74 aprobadas, 291 aserciones. Verificar además en el servidor PHP 8.3/PostgreSQL del negocio.
Build Vite y caché de vistas Blade completados. Pruebas JavaScript nuevas: navegación, límites, teclado, movimiento reducido, redimensionado y eventos de cantidades.

La revisión visual con navegador local no pudo completarse: Chromium no pudo crear su socket en el entorno y el navegador remoto no accedió al servidor local. Revisar anchos 320, 390, 768 y 1366 px, en especial banners con imágenes distintas para celular y escritorio, el enlace de cada promoción y la vista previa en administración.

Al ejecutar toda la batería JavaScript se encontraron 11 fallos anteriores en `order-tracking.test.cjs`: el harness evalúa una directiva Blade `@include` como JavaScript. Reproducidos también con los archivos originales de `main`; no se alteran en esta entrega.
