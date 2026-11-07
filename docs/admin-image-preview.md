# Vista previa de imágenes del administrador

Productos, categorías, adicionales, categorías de adicionales, recomendaciones,
combos, logo y QR de pago muestran la selección antes de guardar. La imagen guardada
continúa visible; la nueva está marcada Sin guardar. Se muestra completa, sin recorte,
con nombre, tamaño y dimensiones si el navegador puede decodificarla.

Quitar selección limpia únicamente el archivo pendiente, devuelve el foco al selector
y no borra la imagen guardada. Cancelar el diálogo nativo conserva la selección anterior.
Tras una recarga o error de validación del servidor, el navegador puede exigir volver
a seleccionar el archivo: no se intenta persistirlo.

Vista previa local mediante Object URL, sin enviar archivos hasta el submit habitual.
Formatos JPG/PNG/WebP y límites de previsualización iguales a los indicados:
2 MB para logo, 5 MB para los demás. Un archivo no compatible, demasiado grande
o ilegible muestra un aviso. El servidor conserva todas sus validaciones; la preview
no garantiza que una subida sea aceptada. No se alteran required, accept ni nombres.

URLs revocadas al cambiar/quitar imagen, error o salida de página; restauración desde
bfcache vuelve a generar la preview. Eventos tardíos de otra selección se ignoran.
Sin JavaScript o soporte Object URL, el formulario nativo sigue funcionando.

## Validación
Nueve pruebas de lógica con DOM simulado en tests/Frontend/admin-image-preview.test.cjs.
Pasaron en V8 con adaptadores de las APIs de Node; no se ejecutó la CLI de Node
ni un navegador real.
Revisión estática de los ocho campos y atributos originales de subida.
PHP/Vite y revisión visual en navegador pendientes: el entorno local no inicia procesos.

```sh
node --test tests/Frontend/admin-image-preview.test.cjs
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Revisar crear/editar en los ocho módulos: selección, reemplazo rápido, cancelar,
quitar selección, PNG transparente, archivo ilegible, límite 2/5 MB y selección
de nuevo tras errores. En Configuración comprobar logo y QR por separado.
Revisar 320/375 px, teclado, ausencia de recorte y que Guardar persiste la imagen.
No requiere migraciones ni dependencias nuevas.
