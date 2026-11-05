# Indexación y caché de páginas privadas

El middleware ProtectPrivateWebResponses se ejecuta al inicio del grupo web y añade estas cabeceras al devolver respuestas de rutas cart.*, checkout.*, customer.*, login, admin.*, delivery.* y orders.tracking.*:

- X-Robots-Tag: noindex, nofollow.
- Cache-Control: private, no-store.
- Referrer-Policy: no-referrer.

Incluye HTML, JSON y redirecciones que atraviesen ese grupo. No altera contenido, estado HTTP, sesiones, autorización ni destinos. Las rutas inexistentes sin nombre o respuestas generadas fuera de Laravel requieren la configuración correspondiente del servidor; no están cubiertas por esta selección.

La elección usa nombres de ruta; parámetros de consulta no pueden cambiarla. Catálogo, platos, combos, sitemap y páginas legales públicas quedan fuera. La protección específica del seguimiento permanece compatible. Las imágenes privadas de cuenta también se sirven sin almacenamiento en caché.

No se añade Disallow a robots.txt: Google debe poder recibir noindex en las páginas accesibles. Noindex no sustituye autenticación ni elimina inmediatamente resultados ya indexados. Referencia oficial: https://developers.google.com/search/docs/crawling-indexing/block-indexing

## Validación

PrivateWebResponsesTest incluye 23 casos: selección de rutas privadas/públicas, cabeceras en JSON, carrito/acceso reales, redirección de cuenta, cuenta autenticada y metadatos públicos del catálogo.

Revisión estática realizada. PHPUnit no ejecutado: Windows bloqueó el arranque de procesos.

```bash
php artisan test --filter='PrivateWebResponsesTest|StorefrontSeoTest|SitemapTest|OrderTrackingPrivacyTest'
php artisan test
```

Sin migraciones, dependencias ni cambios CSS/JS. Verificar con una petición GET en el servidor de pruebas que /carrito tiene las tres cabeceras y / no recibe X-Robots-Tag de esta política. Revisar acceso, compra y vuelta desde el historial; los datos de cuenta deben recargarse del servidor.

Pendientes de cierre público: logo final, vista previa real de Open Graph, dominio/HTTPS en producción, datos legales reales y publicación consciente de las páginas, cookies y revisión móvil. Esta entrega no modifica ni publica contenido legal.
