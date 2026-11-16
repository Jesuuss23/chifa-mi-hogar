# Avisos automáticos de pedidos: Laravel y Android

Estado: implementación en revisión. La recepción de pruebas desde Firebase Console ya fue confirmada por el propietario. Esta integración automática aún necesita ejecutar PHPUnit, compilar Android y probar un pedido real. No se ejecutaron pruebas en el entorno del asistente porque no hay ejecutor disponible.

## Qué ocurre

Al activar Avisos, el teléfono registra su token FCM por HTTPS con su sesión Sanctum. El servidor admite un dispositivo por sesión; cifra el token FCM y conserva un hash único. No acepta un token FCM que ya pertenezca a otra sesión (409). Si ocurre, cerrar la sesión anterior y volver a activar los avisos.

Un pedido nuevo en estado pending guarda avisos pendientes dentro de la transacción de OrderService. El envío no realiza llamadas HTTP en el checkout. El programador de Laravel procesa hasta diez avisos cada minuto, mediante app:send-admin-push, sin un worker adicional. Según la cola pendiente puede tardar más de un minuto. No se avisa de pedidos históricos al habilitar un teléfono.

Se comprueba nuevamente el rol admin, la sesión, su caducidad, el permiso admin-mobile:read, el registro actual y el estado del pedido antes de enviar. La eliminación de la sesión elimina sus dispositivos y avisos pendientes por claves foráneas. El cambio de contraseña/rol ya revoca sesiones en el código existente.

El mensaje solo contiene type y order_id. La app muestra texto genérico; tocar el aviso obtiene los datos con la API autenticada. La clave privada nunca llega a Android.

## Despliegue en el servidor (después de revisar la rama)

La rama feat/admin-order-push parte de feat/admin-firebase-client porque PR #93 y #94 aún estaban abiertos al preparar esta entrega. Contiene la base Android y la integración. No cambiar de rama descartando cambios locales. Primero revisar:

```bash
cd /var/www/chifa-mi-hogar
git status --short
```

Si hay modificaciones, conservarlas/revisarlas antes de cambiar de rama. En un checkout limpio:

```bash
git fetch origin
git checkout feat/admin-order-push
composer install --no-interaction --prefer-dist --optimize-autoloader
```

Mantener ADMIN_PUSH_ENABLED=false durante la migración. La credencial ya preparada debe estar en /etc/chifa-mi-hogar/firebase-admin.json, propiedad root:www-data, modo 640, directorio 750. Confirmar que el proceso del scheduler también tiene acceso; no imprimir su contenido.

Antes de las pruebas, comprobar que no hay configuración Laravel de producción cacheada en el entorno de pruebas. El phpunit.xml del repositorio usa SQLite :memory:. Las pruebas usan RefreshDatabase: ejecutarlas en un entorno de pruebas, nunca apuntando a PostgreSQL de producción.

```bash
php artisan test --filter='AdminPushTest|FirebaseAdminMessagingTest|AdminMobileApiTest'
```

Después de las pruebas y revisión, aplicar en producción:

```bash
php artisan migrate --force
```

Configurar en .env:

```dotenv
ADMIN_PUSH_ENABLED=true
FIREBASE_CREDENTIALS=/etc/chifa-mi-hogar/firebase-admin.json
```

```bash
php artisan config:cache
php artisan schedule:list
```

El scheduler existente de WhatsApp debe ejecutar schedule:run cada minuto. Comprobar que también aparece app:send-admin-push; no crear un cron duplicado si ya existe. El usuario del scheduler necesita leer la credencial y escribir los logs/cache de Laravel. El envío usa Firebase HTTP v1 y OpenSSL de PHP; no añade paquetes Composer ni APIs de pago.

## Actualizar Android

En el checkout de Windows, con cambios locales conservados:

```bat
git fetch origin
git -c core.protectNTFS=false checkout feat/admin-order-push
```

Abrir mobile/admin en Android Studio. Sincronizar Gradle, compilar y ejecutar:

```powershell
.\gradlew.bat :app:testDebugUnitTest :app:lintDebug :app:assembleDebug
```

Iniciar sesión → Avisos → Permitir avisos. Debe decir **Teléfono registrado para recibir pedidos**. No basta con tener la versión anterior que solo recibía pruebas desde la consola. La app intenta sincronizar al restaurar sesión, volver al primer plano y cuando Firebase rota su token. Sin conexión se reintenta al volver a abrir/activar; no se promete registro inmediato si Android termina el proceso.

Desactivar avisos funciona localmente aunque falte conexión; la baja del servidor se reintenta al volver a abrir la app. Cerrar sesión requiere confirmar revocación como antes.

## Prueba completa

1. Registrar el teléfono con la app nueva, tras habilitar Laravel.
2. Crear un pedido nuevo desde la web. La compra debe funcionar sin esperar a Firebase.
3. Esperar la siguiente ejecución del scheduler o ejecutar manualmente:
   `php artisan app:send-admin-push`
4. Probar recepción en primer plano, segundo plano y pantalla bloqueada; al tocar, abrir el pedido correcto.
5. Ejecutar otra vez el comando: un aviso marcado sent no se vuelve a enviar.
6. Desactivar avisos, crear otro pedido y comprobar que ese teléfono no lo recibe.
7. Repetir con dos teléfonos y cerrar una sesión: la otra debe continuar activa.
8. Probar expiración/revocación y pérdida de conexión en un entorno de pruebas.

Diagnóstico sin credenciales:
```sql
SELECT status, result, COUNT(*) FROM admin_push_messages GROUP BY status, result;
```

sent significa que FCM aceptó el envío, no que el usuario lo haya visto.
credentials_or_oauth indica archivo/clave/acceso OAuth incorrecto; http_403 puede indicar API o permisos de Firebase. Verificar proyecto y permiso de envío de la cuenta de servicio. No compartir claves ni tokens.

## Reintentos y límites

- Una actualización atómica impide que dos ejecuciones reclamen el mismo aviso pendiente.
- 429/503: hasta tres intentos, con Retry-After, espera creciente y variación aleatoria.
- Error de red ambiguo: uncertain, sin reenvío automático para evitar duplicados.
- Proceso interrumpido durante más de diez minutos: uncertain, sin reenvío automático.
- Pedidos entregados, cancelados o de más de quince minutos: skipped. Un token rotado no recibe avisos anteriores pendientes.
- UNREGISTERED elimina solo el registro que respondió inválido, sin borrar uno recién rotado.
- Una revocación puede coincidir con un envío ya en vuelo: el payload es genérico y los detalles siguen requiriendo autorización.
- La app usa una etiqueta por pedido para reemplazar la notificación visible. No es una garantía de entrega exactamente una vez.
- Firebase Console puede mostrar sus mensajes directamente en segundo plano; las pruebas de consola deben contener solo texto genérico.
- Android puede retrasar mensajes por red, batería o detención forzada. La lista de pedidos sigue siendo la fuente de información.
- Los registros de avisos se eliminan a los treinta días. El envío queda desactivado por defecto.
- La ubicación se incorporará al iniciar un reparto; no se solicita para consultar pedidos.

Para desactivar la función: ADMIN_PUSH_ENABLED=false y php artisan config:cache. No borrar tablas ni revocar la clave como parte de una desactivación rutinaria.
