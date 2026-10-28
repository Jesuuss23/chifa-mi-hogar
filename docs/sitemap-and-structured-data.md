# Sitemap y datos estructurados

## Alcance

- GET /sitemap.xml: XML con portada, catálogo de combos y fichas actualmente públicas.
- Platos: disponibles y con categoría activa existente. Combos: disponibles y visibles en menú.
- Soft deletes respetados. Lista de rutas explícita: no se consultan pedidos, usuarios ni tokens.
- Sin filtros, parámetros de seguimiento, paginaciones, páginas legales ni áreas privadas en el sitemap.
- Sin lastmod: la fecha de la fila no representa todos los cambios de contenido (relaciones/imagen).
- Sin caché de catálogo: despublicar retira la ficha en la siguiente petición.
- Lectura por lotes de 500; más de 50.000 URLs o 50 MB devuelve 503, nunca un XML truncado. Si el negocio llega a ese tamaño, implementar índice de sitemaps.
- robots.txt mantiene el rastreo permitido e indica https://chifamihogar.com/sitemap.xml. Si cambia el dominio, actualizar también este archivo estático y la configuración de identidad.
- Los metadatos y sitemap comparten baseUrl de StorefrontSeoService, independiente del Host de la petición.

## JSON-LD

WebSite, CollectionPage/WebPage y BreadcrumbList describen el sitio y la jerarquía del catálogo.
Los cuatro tipos de vistas públicas con SEO reciben un solo bloque JSON-LD.
No se añaden datos de Restaurant, precios/ofertas, reseñas, horarios o dirección no comprobados.
Se reutilizan las URL canónicas y los textos de metadatos. JSON_HEX_* protege contra cierres de script.
No se incorpora JavaScript ejecutable ni servicios externos.

## Despliegue y verificación

No requiere migraciones, paquetes nuevos ni compilación de assets.

```bash
php artisan route:clear
php artisan view:clear
php artisan test --filter='SitemapTest|StorefrontStructuredDataTest|StorefrontSeoTest|BrandShareImageTest'
php artisan test --filter='Checkout|OrderTrackingPrivacy|BrandSettings|LegalPages'
```

Si producción utiliza caché de rutas, ejecutar php artisan route:cache después de verificar.
Visitar /sitemap.xml y /robots.txt con el dominio definitivo; comprobar que Nginx/Apache no intercepta el XML con una regla para archivos estáticos.
Validar una ficha con Rich Results Test (BreadcrumbList) y la portada con Schema Markup Validator.
Enviar https://chifamihogar.com/sitemap.xml en Google Search Console al desplegar.
El sitemap y el marcado no garantizan indexación ni resultados enriquecidos.

Pruebas nuevas: 5 de sitemap y 5 de datos estructurados. Se adapta la prueba XSS existente para permitir únicamente el script JSON-LD.
En el entorno del asistente no se pudo ejecutar PHP/PHPUnit por fallo de aprovisionamiento del sandbox de Windows; ejecución pendiente en el servidor.

## Referencias

- https://developers.google.com/search/docs/crawling-indexing/sitemaps/build-sitemap
- https://developers.google.com/search/docs/appearance/structured-data/breadcrumb
- https://developers.google.com/search/docs/appearance/site-names
