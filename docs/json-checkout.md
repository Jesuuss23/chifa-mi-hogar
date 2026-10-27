# Checkout JSON

Las rutas existentes conservan sus nombres, middleware y campos de respuesta.
El checkout HTML del carrito mantiene su token de sesión independiente.

## Contrato

1. Enviar POST /checkout/quote con los datos existentes de cotización.
2. Leer checkout_token (nuevo campo superior de la respuesta).
3. Enviar ese token junto con los datos de cliente y artículos a POST /checkout
   o POST /admin/checkout. Esta última ruta requiere un administrador autenticado.
4. Conservar las mismas cookies de sesión y las protecciones CSRF de las rutas web.
   No son endpoints autenticados mediante un token Bearer.

La cotización sigue devolviendo message, quote e items.
El registro sigue devolviendo HTTP 201 con message y order.
Los precios se recalculan en el servidor al registrar.

## Reintentos y nueva compra

- Repetir el registro con el mismo token y sesión devuelve el pedido ya creado;
  no crea otro pedido ni vuelve a programar su notificación.
- Se sigue validando el formato del formulario en cada registro.
- Un reintento no modifica los artículos, datos o importes del pedido existente,
  aunque el cliente envíe otros datos válidos.
- Cotizar otra vez antes de registrar conserva el token.
- Cotizar después de registrar emite un nuevo token para la siguiente compra.
  Desde ese momento el token anterior ya no es válido en esa sesión.
- Una cotización fallida no renueva el token.
- Cada sesión mantiene una sola compra JSON activa; varias pestañas comparten
  este estado. No se promete idempotencia de cotizaciones simultáneas.
- Sin token o con UUID inválido: HTTP 422.
- Token válido pero ausente o distinto en sesión: HTTP 419.
- Un token del checkout HTML no autoriza el checkout JSON.

El checkout JSON antes no podía crear pedidos porque no entregaba al servicio
el checkout_token obligatorio. Un consumidor externo, si existe, debe adoptar
la secuencia cotizar -> registrar descrita arriba. No se encontraron consumidores
de estas rutas JSON en las vistas o JavaScript del repositorio.

No se modifican las opciones de entrega/pago de estos endpoints en este parche.
No hay migraciones ni servicios externos nuevos.

## Pruebas

Con la base de pruebas SQLite en memoria definida en phpunit.xml:

```bash
php artisan test --filter=JsonCheckoutTokenTest
php artisan test --filter=CartCheckoutConfirmationTest
php artisan test --filter=AdminOrderPaymentAndDispatchTest
```

Estas pruebas usan RefreshDatabase y no deben apuntar a producción.
