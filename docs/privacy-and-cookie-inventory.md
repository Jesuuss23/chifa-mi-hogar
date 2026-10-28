# Páginas informativas e inventario de almacenamiento

## Alcance de la revisión

Revisión estática del repositorio, base main 374c1c5 (28/10/2026). Se revisaron 88 archivos entre vistas, JavaScript propio, controladores, servicios y configuración de sesión. No se inspeccionó un navegador en producción ni su CDN, proxy, extensiones, contenido inyectado o configuración .env.

No se encontraron Google Analytics, Tag Manager, Meta Pixel, cookies opcionales propias, document.cookie, localStorage ni sessionStorage en el código propio revisado. Esto no certifica que la infraestructura externa no añada cookies.

## Inventario

| Elemento | Origen / uso | Duración / observación |
| --- | --- | --- |
| Cookie de sesión | Laravel; nombre obtenido de config(session.cookie), configurable con SESSION_COOKIE | SESSION_LIFETIME, 120 minutos por defecto; cierre del navegador si SESSION_EXPIRE_ON_CLOSE=true |
| XSRF-TOKEN | Laravel; protección de formularios y solicitudes | Minutos configurados para la sesión; se renueva con respuestas |
| Carrito y tokens de checkout | Sesión Laravel del servidor con driver database por defecto | No son cookies individuales ni localStorage |
| Cuenta / sesión autenticada | Laravel, sin remember=true en los logins revisados | No se identificó cookie persistente de “recordarme” |
| Mapas | Leaflet local empaquetado por Vite; imágenes de OpenStreetMap desde el navegador | Petición externa con IP y área de teselas; caché HTTP no equivale a cookies |
| Rutas | Geoapify desde DeliveryRouteService | Coordenadas de origen/destino enviadas desde el servidor; caché de ruta de hasta 24 h |
| WhatsApp | API Meta desde el servidor | Avisos a destinatarios configurados; pueden incluir datos personales del pedido |
| Google Places | Servicio consultado por administración | Obtiene reseñas del negocio desde el servidor; no inserta un widget de analítica |
| Enlaces externos | WhatsApp, Google Maps, reseñas y redes configuradas | El navegador visita el proveedor al abrir el enlace |
| GPS | API geolocation del navegador | Permiso del navegador; coordenadas de entrega / repartidor se guardan en el pedido |
| Turnstile, si se incorpora PR #9 y se activa | Script del navegador y Siteverify desde servidor | La política lo menciona solo cuando config(turnstile.enabled) es true; documentar también la configuración real del widget y CDN |

Las páginas muestran el nombre y tiempo de sesión de la configuración ejecutada, no valores fijos.

## Consentimiento

Esta entrega informa de cookies necesarias y servicios operativos. No añade un banner de aceptar/rechazar sin categorías opcionales reales, ni declara que cargar la web constituye consentimiento. No recopila aceptación para publicidad.

Si se incorpora analítica, publicidad u otro servicio opcional, antes de activarlo se necesita inventariarlo y añadir control previo efectivo, rechazo, persistencia/versionado de preferencia y revocación. Los scripts y peticiones opcionales deben permanecer bloqueados sin aceptación. No basta ocultar un banner.

Antes de publicar, contrastar el inventario con Network y Storage del navegador en el dominio desplegado, incluyendo registro con Turnstile activo, checkout y seguimiento. Revisar cookies introducidas por CDN/WAF/hosting. No se efectuó aquí esa comprobación en vivo.

## Páginas y datos pendientes

Nueva sección en Administración → Configuración general:
- Domicilio del responsable.
- Alojamiento y otros encargados: identidad, ubicación/país y finalidad.
- Plazos de conservación por categoría y procedimiento real de eliminación.
- Identificación de bancos de datos y titular; inscripción solo cuando se haya confirmado.

Se puede guardar borrador. La vista previa está restringida a auth/admin, con noindex y private/no-store. Publicar exige esos campos y la identidad básica; activar la casilla expone /aviso-legal, /privacidad y /cookies y sus enlaces. Desactivar retira las páginas públicas (404) y sus enlaces. Los datos se escapan como texto, sin HTML administrativo ejecutable.

No se inventan dirección, proveedor, plazos ni RUC. El RUC nulo se omite en público y se señala en la vista previa. La validación de campos completos no certifica suficiencia legal ni veracidad. Los textos son una base para revisión por el responsable conforme al tratamiento real. Guardar plazos NO implementa borrado automático. Definir y aplicar la retención es un pendiente operativo.

Esta entrega tampoco reemplaza términos de compra detallados, Libro de Reclamaciones ni las obligaciones que correspondan al negocio.

## Ajuste de mapas descubierto en la revisión

OpenStreetMap exige Referer en sus imágenes de mapas. El no-referrer de seguimiento podía suprimirlo. Se conserva esa política para la página y se limita la excepción a TileLayer con referrerPolicy: origin: el proveedor recibe únicamente el origen, sin ruta ni token del pedido.

Se usa la URL oficial https://tile.openstreetmap.org/{z}/{x}/{y}.png y atribución enlazada. Se mantiene Leaflet, el proveedor gratuito, el flujo de mapas y los marcadores. Verificar en navegador que el Referer de las imágenes contiene solo el origen y que no se filtra el token.

## Instalación y pruebas

php artisan migrate
php artisan view:clear
npm run build
php artisan test --filter=LegalPagesTest
php artisan test --filter='Checkout|OrderTrackingPrivacy|BrandSettings|AuthenticationRateLimit|RegistrationTurnstile'
node --test tests/Frontend/order-tracking.test.cjs

19 casos nuevos: borradores privados, previews, publicación, validación de datos, permisos, despublicación, configuración de cookies, escape HTML y ausencia de RUC ficticio, aviso condicional de Turnstile, enlaces condicionados y retirada por datos incompletos.

No se ejecutaron PHPUnit, Node, compilación ni navegador: el entorno bloquea el inicio de procesos por permisos. Se realizó revisión estática. Base remota observada sin PR #9; este cambio no lo copia ni sustituye, y debe fusionarse junto a él.

## Referencias revisadas

- Ley 29733, artículo 18 (deber de información): https://www.congreso.gob.pe/Docs/DGP/DIDP/files/ley_29733.pdf
- Reglamento DS 016-2024-JUS: https://www.congreso.gob.pe/Docs/DGP/DIDP/files/ds_016-2024-jus.pdf
- Opinión ANPD sobre cookies: https://www.gob.pe/institucion/anpd/informes-publicaciones/2670212-oc-n-02-2022-jus-dgtaipd-sobre-disposiciones-de-la-ley-n-29733-ley-de-proteccion-de-datos-personales-y-su-reglamento-derecho-de-informacion-transferencia-de-datos-y-tratamiento-de-datos-a-traves-de-cookies
- Política de mapas: https://operations.osmfoundation.org/policies/tiles/
- Opción de referencia por imagen: https://leafletjs.com/reference.html#tilelayer-referrerpolicy
