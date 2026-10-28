# SEO público y Open Graph

## Alcance

Metadatos para portada/categorías, detalle de platos, listado de combos y detalle de combos.
El HTML visible, configuradores, formularios, precios, APIs y flujos de compra se conservan.

Cada página tiene un solo título, descripción y canonical, más Open Graph y Twitter Card:
- Inicio: Chifa Mi Hogar | Dark Kitchen de chifa en SJL.
- Descripción: Hoy se antoja chifa. Descubre nuestros platos y combos y pide tu favorito a domicilio en San Juan de Lurigancho, Lima.
- Detalles: nombre y descripción del catálogo, normalizados como texto y escapados en Blade.
- Categoría y página > 1 se conservan en canonical. Se eliminan parámetros ajenos, como utm_source.
- La identidad absoluta usa el sitio web guardado en Configuración, no el Host de la petición. Se toma su origen HTTPS (dominio y puerto, sin rutas, credenciales ni query).
- Si la configuración es inválida, la base es https://chifamihogar.com.

No se añaden OG ni canonical de catálogo a carrito, checkout, cuentas, seguimiento, autenticación o vistas previas administrativas. Se conservan el noindex y la política de privacidad del seguimiento. Esta entrega no hace una nueva auditoría de indexación de todos los paneles.

## Imagen de marca

Endpoint público sin sesión: /api/brand/share-image.jpg.
Usa GD, ya utilizado por ImageService/Intervention. No requiere paquetes, fuentes descargadas ni proveedores nuevos.

JPEG de 1200 × 630, fondo crema y texto terracota:
- Utiliza la variante detail del logo subido a administración.
- Si falta el archivo de esa variante, intenta public/images/brand/logo.jpeg.
- Sin imagen válida, muestra una tarjeta tipográfica de Chifa Mi Hogar y Dark Kitchen.
- No recorta el logo y limita ampliación.
- Para imágenes del catálogo se conserva el WebP público existente, con alternativa a la marca si el archivo falta.

La versión de la URL depende del contenido del logo. La imagen generada se guarda en caché por 24 horas; la respuesta HTTP permite caché por una hora. No se expone la ruta original ni se aceptan parámetros para leer archivos o consultar URLs externas. El endpoint siempre sirve la marca actual; una URL antigua no permite recuperar versiones privadas.

El original manual debe ser JPEG/PNG/WebP válido, máximo 5 MB, 4096 por lado y 8 megapíxeles para este generador. El formulario de logo conserva su validación más estricta de 2 MB. Archivos corruptos o excesivos usan la tarjeta alternativa.

Los JPEG sencillos son pequeños; se prueba la tarjeta predeterminada bajo 300 KB. El tamaño final depende del logo real. Las imágenes propias de platos conservan su optimización existente.

Administración → Configuración → Identidad ofrece «Ver imagen para compartir».
Los servicios sociales pueden conservar sus propias previsualizaciones en caché; no se garantiza actualización inmediata ni posicionamiento.

## Despliegue

No requiere migraciones ni dependencias nuevas.

php artisan route:clear
php artisan view:clear

Si se usa caché de rutas, regenerarla después con php artisan route:cache según el procedimiento habitual del servidor.
Verificar APP_URL=https://chifamihogar.com y el sitio web de Configuración. El almacenamiento público debe seguir accesible (storage:link existente).

Validación en el entorno de pruebas:
php artisan test --filter='StorefrontSeoTest|BrandShareImageTest'
php artisan test --filter='Checkout|OrderTrackingPrivacy|BrandSettings|LegalPages'

16 casos nuevos: metadatos únicos, filtros/paginación, imágenes y fallback, combos, escape de datos, Host no confiable, exclusión de páginas privadas, JPEG sin sesión, dimensiones, peso predeterminado, logo real y cambio de versión, imagen inválida y parámetros ignorados.

No se ejecutaron PHPUnit ni php -l, y no se renderizó una vista previa visual del JPEG: el entorno rechazó crear procesos por permisos. La revisión fue estática. Antes de fusionar, abrir «Ver imagen para compartir», comprobar el logo y compartir una URL pública desde el dominio HTTPS. Las pruebas no sustituyen comprobar cómo presenta cada plataforma la tarjeta.

## Fuera de esta entrega

Sitemap, datos estructurados de negocio, rediseño/paleta global y textos comerciales adicionales.
El dominio, logo y contenidos existentes siguen siendo administrables como antes.

Referencias:
- https://ogp.me/
- https://developers.google.com/search/docs/crawling-indexing/consolidate-duplicate-urls
