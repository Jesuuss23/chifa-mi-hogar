# Notificaciones: prueba del cliente Android

Esta entrega conecta Firebase Messaging a la app administrativa y solicita el permiso al pulsar **Avisos → Permitir avisos**, después de iniciar sesión. Rechazarlo no bloquea los pedidos. No pide ubicación: se incorporará cuando el administrador inicie un reparto.

**Todavía no registra dispositivos en Laravel ni envía avisos automáticamente por cada pedido.** El siguiente paso requiere una credencial de servicio de Firebase instalada únicamente en el servidor y endpoints autenticados de registro/revocación de dispositivos.

## Configuración y compilación

Se utiliza el archivo existente `app/google-services.json`, cuyo paquete debe ser `com.chifamihogar.admin`. Es configuración del cliente; no reemplaza la credencial privada del servidor. Nunca colocar una clave de cuenta de servicio en la app ni subirla al repositorio.

Abrir `mobile/admin` en Android Studio, sincronizar Gradle y ejecutar:

```powershell
.\gradlew.bat :app:testDebugUnitTest :app:lintDebug :app:assembleDebug
```

La compilación y las pruebas de esta entrega deben completarse en Android Studio antes de aprobarla. Se incluye una prueba de validación del identificador de pedido.

## Enviar un aviso de prueba

1. Usar un teléfono o emulador con Google Play y conexión a Internet.
2. Instalar la variante debug e iniciar sesión como administrador.
3. Abrir **Avisos**, pulsar **Permitir avisos** y aceptar el permiso de Android. Si ya fue denegado, usar **Abrir ajustes del teléfono**.
4. Pulsar **Copiar token de prueba** (solo aparece en debug).
5. En Firebase Console, Messaging, crear una notificación con título «Chifa Mi Hogar» y texto genérico «Prueba de notificaciones». En **Enviar mensaje de prueba**, pegar el token del teléfono. No publicarlo ni enviarlo por chat.
6. Probar con la app abierta, en segundo plano y con pantalla bloqueada. Tocar el aviso debe abrir la app.
7. Para probar la apertura de un pedido concreto, enviar el campo de datos `order_id` con el identificador numérico de un pedido existente. La app consulta su detalle por la API autenticada; nunca toma precios, clientes ni estados del mensaje.
8. Denegar el permiso y comprobar que se puede seguir consultando pedidos. Probar **Desactivar avisos**, volver a activarlos y cerrar sesión.

La prueba desde la consola utiliza mensajes de notificación que Firebase puede mostrar directamente en segundo plano. Usar solo texto genérico, sin datos de clientes. Un mensaje en tránsito puede aparecer después de desactivar; el cierre de sesión no garantiza retirar mensajes ya entregados por Firebase.

La integración automática del servidor deberá utilizar mensajes **solo de datos** (`type=new_order`, `order_id=<id>`), asociados a una sesión administrativa vigente. La app filtra estos avisos si no están activados o no hay sesión local vigente. El servidor debe comprobar permisos y revocación antes de enviarlos; una sesión revocada remotamente no se detecta en el teléfono hasta consultar la API.

Los avisos de un mismo pedido utilizan una etiqueta estable para reemplazar la notificación visible; esto no constituye una garantía de entrega exactamente una vez. No se solicitan exclusiones de batería ni ubicación en segundo plano. Forzar la detención de la app puede impedir recibir mensajes hasta volver a abrirla.

## Siguiente entrega: Laravel

- Registro y rotación del token FCM ligados al token Sanctum del teléfono.
- Revocación al cerrar sesión o perder el rol administrativo.
- Envío tras confirmar la transacción del pedido, mediante cola y Firebase HTTP v1.
- Reintentos limitados y eliminación de tokens inválidos; payload genérico sin información del cliente.
- Credencial privada fuera del directorio público y del repositorio, configurada únicamente en el servidor.

Esta entrega no cambia Laravel ni necesita migraciones.
