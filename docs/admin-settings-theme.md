# Tema de configuración administrativa

Configuración general, identidad y páginas informativas reutilizan la paleta y controles del panel mediante catalog-surface y admin-catalog-surface. Se agrega el título de página, se elimina el aviso de éxito duplicado (lo muestra layouts.admin), se marca el resumen de errores como alerta y se asocia la etiqueta del enlace de reseñas a su campo.

El QR conserva fondo blanco y se limita al ancho disponible. Los formularios de identidad, páginas informativas y configuración operativa siguen separados; cada uno mantiene su botón de guardado. Las rutas, campos, límites, CSRF, métodos PUT, valores anteriores y condiciones Blade se conservan. No cambia la lógica de pagos, delivery, apertura, publicación legal, carga de imágenes ni APIs.

## Verificación

Comparación estática de las tres vistas: rutas, atributos de formularios/validación y directivas Blade iguales, salvo la eliminación intencional del aviso de éxito duplicado. Identificadores sin duplicados y etiquetas asociadas a campos existentes; el bucle legal conserva sus campos dinámicos.

No se ejecutaron PHP, Vite ni navegador desde el entorno de trabajo. No se agregan pruebas que solo comparen clases CSS.

```sh
php artisan test
npm ci
npm run build
php artisan view:clear
```

No requiere migraciones.

En móvil y escritorio, comprobar:

- Guardar identidad con y sin logo nuevo; verificar que el aviso de éxito aparece una sola vez.
- Guardar borrador legal, abrir vistas previas y comprobar el control de publicación.
- Guardar configuración operativa, QR y enlace de reseñas.
- Provocar un error de validación y comprobar resumen de errores y conservación de valores.
- Revisar fondo blanco del QR, campos numéricos, casilla de apertura y navegación con teclado.
