# Revisión de seguridad: carrito, checkout y referidos

Fecha: 15 de noviembre de 2026. Base: `feat/referral-discounts` (PR #91, commit `c48ab5a`). Esta revisión no certifica ausencia de vulnerabilidades ni sustituye una revisión del servidor en producción.

## Hallazgos y correcciones

| Hallazgo | Evidencia y cambio |
| --- | --- |
| El carrito podía crecer por encima del límite aceptado por checkout | Se reprodujo con platos, combos y unidades personalizadas. Ahora todas las inserciones validan un máximo de 30 configuraciones antes de guardar la sesión; un lote que excede el límite se rechaza completo. Se permite eliminar artículos de un carrito antiguo sobredimensionado. |
| Las modificaciones del carrito carecían de límite de frecuencia | Límite compartido de 60 modificaciones/minuto por cuenta, o por IP para invitados. Respuesta 429 al excederlo; no afecta las lecturas. Los invitados en una misma IP comparten el límite. |
| Las cargas grandes podían generar trabajo excesivo de validación | Límite de 128 KiB del cuerpo real en rutas de carrito y checkout; respuesta 413. Se rechaza multipart con 415 en esas rutas, que no reciben archivos. Los formularios de imágenes conservan sus propias rutas. Se limita también el array tradicional de adicionales. Esto no reemplaza los límites de cuerpo/conexiones de Nginx/PHP. |
| Faltaba comprobar nuevamente el propietario al reutilizar referencias de checkout | Defensa adicional: después de validar la referencia/token de sesión, confirmación y consulta de estado exigen que un pedido registrado pertenezca a la cuenta actual. Se prueban referencias antiguas con otra cuenta y sin login: 404. Los pedidos invitados siguen ligados a su sesión. No se demostró acceso externo adivinando IDs; el logout normal ya invalida la sesión. |
| Dependencias con avisos conocidos | Composer reportó 9 avisos en 6 paquetes. Se actualizaron versiones compatibles del lockfile. npm señaló follow-redirects y su dependencia ascendente; se actualizó la dependencia transitiva. Auditorías finales: cero avisos conocidos en Composer y npm, incluyendo dependencias de desarrollo. No se demostró explotación de esos avisos en esta aplicación. |

## Integridad comprobada

- Precios, descuentos y total se calculan en servidor; valores enviados por el cliente no sustituyen esos cálculos.
- Enviar otro user_id, un rol o un payment_status no convierte un pedido nuevo en pagado ni cambia su propietario.
- Adicionales ajenos y cantidades negativas o con tipo inválido se rechazan.
- En PostgreSQL, cuatro solicitudes simultáneas con el mismo token crean un pedido; cuatro verificaciones de pago generan un cupón; cuatro pedidos distintos intentando usar el mismo cupón permiten un solo uso; cuatro cancelaciones liberan una reserva.
- Se revisaron las rutas protegidas, autorización de reparto, seguimiento, autenticación, manejo de perfiles y webhook firmado. Las pruebas relacionadas forman parte de la selección ejecutada; no se realizó un ataque contra producción.

## Dependencias actualizadas

| Paquete | Antes | Después |
| --- | --- | --- |
| laravel/framework | 12.70.1 | 12.72.0 |
| symfony/http-foundation | 7.4.21 | 7.4.23 |
| symfony/http-kernel | 7.4.21 | 7.4.23 |
| symfony/mime | 7.4.19 | 7.4.22 |
| symfony/routing | 7.4.21 | 7.4.23 |
| symfony/yaml | 7.4.21 | 7.4.23 |
| follow-redirects | 1.16.1 | 1.16.3 |

Los avisos dependen de condiciones de uso: por ejemplo, no se encontró uso de Concurrency::run en la aplicación durante esta revisión. Un aviso en una dependencia no prueba que se hayan filtrado datos.

## Validación realizada

- 126 pruebas PHP seleccionadas, 1002 aserciones: aprobadas. Incluyen CheckoutSecurityTest, referidos, checkout HTML/JSON, límites de autenticación, autorización GPS, privacidad de seguimiento y autorización de comandos WhatsApp.
- 96 pruebas frontend: aprobadas. Se corrigió el arnés antiguo de order-tracking para cargar los parciales Blade reales y comprobar el temporizador actual; no se alteró el comportamiento de producción para hacer pasar la prueba.
- Cuatro escenarios de concurrencia PostgreSQL con cuatro procesos cada uno: aprobados en una base aislada, sin envíos reales.
- Compilación Vite, caché Blade y git diff --check: aprobados.
- Composer audit --locked y npm audit: cero avisos conocidos al momento de consultar.
- Suite PHP completa de 786 casos: NO concluida. El registro alcanzó 567 casos con una omisión y sin fallos registrados hasta ese punto; la revisión automática del entorno interrumpió el proceso por una conexión auxiliar de telemetría de Microsoft. No se reintentó esa conexión. Ejecutar la suite completa en un entorno de pruebas antes de fusionar.

## Prueba y despliegue

El PR de seguridad depende del #91. Revisar primero #91; después cambiar la base del PR de seguridad a main cuando #91 esté integrado. No fusionar esta rama sobre una versión de referidos distinta sin revisar los cambios.

En una copia de pruebas con base de datos de pruebas (nunca usar la base de ventas para ejecutar tests):

```bash
git fetch origin
git checkout fix/checkout-security
composer install
npm ci
npm run build
php artisan test
node --test tests/Frontend/*.test.cjs
composer audit --locked
npm audit
```

En producción, después de aprobar y fusionar, instalar los lockfiles con `composer install --no-dev --optimize-autoloader`, compilar assets y ejecutar `php artisan optimize:clear` y `php artisan queue:restart`. Este parche de seguridad no agrega migraciones; #91 sí contiene una migración de referidos que debe aplicarse al desplegar esa funcionalidad.

## Límites y siguientes controles

- Cuentas diferentes no demuestran personas diferentes. Evitar pérdidas por autorreferidos/multicuentas requiere revisar pagos reales y definir reglas comerciales adicionales. Este parche no introduce verificación de teléfono ni un presupuesto automático por campaña.
- Los porcentajes configurados deben respetar el margen del negocio. Las pruebas técnicas no garantizan rentabilidad; el descuento no es una prueba de pago.
- El límite por IP requiere una configuración correcta de proxies confiables y una caché persistente/compartida. No se inspeccionó la configuración del VPS, Nginx, TLS, backups, permisos, claves ni el historial completo de secretos.
- Los enlaces públicos de seguimiento son credenciales de acceso por posesión: quien recibe un enlace válido puede consultar lo que ese seguimiento expone. No deben publicarse ni enviarse a personas ajenas al pedido.
- No se efectuaron pruebas de carga, escaneo ofensivo de producción ni una auditoría exhaustiva de todas las funciones de la aplicación.
