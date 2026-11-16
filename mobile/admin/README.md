# Mi Hogar Admin — Android, entrega 1

Proyecto nativo Kotlin + Jetpack Compose para abrir en Android Studio. Consume la API Laravel existente: `https://chifamihogar.com/api/v1/admin/`. No requiere crear otra base de datos ni contratar servicios.

**Estado: base Android compilada y probada por el propietario. La recepción Firebase anterior fue confirmada por el propietario. Esta entrega conecta Laravel y está pendiente de pruebas PHP, compilación Android y prueba real.** Ver [FIREBASE.md](FIREBASE.md) para configuración, pruebas y límites.

## Alcance

- Acceso con la cuenta administrativa existente (correo y contraseña).
- Restauración de sesión consultando `/auth/me`; token con caducidad de 30 días definida por Laravel.
- Pedidos paginados, búsqueda por código/cliente y filtro por estado.
- Detalle: cliente, entrega, repartidor, platos, adicionales, componentes de combos, pago y totales históricos del servidor.
- Actualización manual y al volver al primer plano, estados de carga y reintento.
- Cierre de sesión mediante revocación del token actual.

Esta entrega consulta pedidos. Verificar pagos, cambiar estados, asignar repartidores, registrar ventas son siguientes entregas; no se simulan acciones locales que el servidor todavía no ofrece.

## Abrir en Android Studio

1. Obtener la rama `feat/admin-order-push` en una copia local del repositorio.
2. En Android Studio, **Open** y seleccionar `mobile/admin`, no la raíz Laravel.
3. Usar una versión de Android Studio compatible con AGP 9.3; configurar Gradle JDK 21 y aceptar la sincronización. El wrapper fija Gradle 9.5.0 y verifica el SHA-256 de su distribución.
4. En SDK Manager instalar Android SDK Platform 36 y Build Tools 36.0.0. Android Studio generará `local.properties` con la ruta de tu SDK; ese archivo no se publica.
5. Ejecutar los comandos siguientes desde `mobile/admin`, con conexión a Google Maven/Maven Central/Gradle para la primera descarga.
6. Seleccionar un emulador o teléfono con Android 8.0 (API 26) o superior y pulsar Run. Para probar en un teléfono, habilitar depuración USB.

PowerShell:

```powershell
.\gradlew.bat :app:testDebugUnitTest :app:lintDebug :app:assembleDebug
```

Linux/macOS:

```bash
./gradlew :app:testDebugUnitTest :app:lintDebug :app:assembleDebug
```

Cuando la compilación termine correctamente, la APK de depuración estará en `app/build/outputs/apk/debug/app-debug.apk`. No se adjunta una APK porque aún no se ha validado la compilación. Para distribución interna final se generará una APK release firmada, con claves fuera del repositorio.

No pegar claves de Laravel, credenciales del servidor ni contraseñas en Gradle o en el código. El administrador escribe sus credenciales únicamente en la pantalla de login.

## Seguridad implementada

- HTTPS obligatorio; certificados del sistema, sin aceptar certificados inválidos ni seguir redirecciones con el token.
- Token cifrado con AES-GCM y clave de Android Keystore. Solo el cifrado va al directorio `noBackupFilesDir`; backups y transferencia de datos se excluyen además por XML.
- Contraseña y pedidos permanecen en memoria, sin caché HTTP ni base de datos local. No se registran tokens, respuestas o contraseñas en logs. El texto de cliente/notas se muestra como texto Compose, nunca como HTML.
- Respuestas limitadas a 4 MiB y timeouts de conexión/lectura. Sin reintentos automáticos del login. Mientras hay una operación, se impiden envíos duplicados desde la interfaz.
- 401/403 limpian la sesión y los pedidos visibles. 429 respeta `Retry-After` numérico, con espera predeterminada de 60 segundos si falta. El servidor conserva la autoridad sobre permisos y expiración.
- `FLAG_SECURE` bloquea capturas y previsualizaciones sensibles en dispositivos que respetan esa bandera. No protege frente a un sistema operativo comprometido.
- Solo permiso de Internet. No se solicita GPS, cámara, archivos ni notificaciones en esta entrega.

Cerrar sesión necesita conexión para confirmar la revocación. Si falla la red, se informa el error y la sesión permanece; reintentar **Salir** al recuperar conexión. No se afirma haber revocado un token sin respuesta del servidor. Si el token ya fue revocado, el siguiente 401 elimina la copia local.

## Validación

Realizado: revisión del contrato de la API existente, validación sintáctica de los XML y comparación del hash del wrapper con el publicado por Gradle. No se usaron credenciales reales ni se alteraron pedidos.

Pendiente: compilación Android, seis pruebas unitarias de contrato incluidas, Android Lint y prueba funcional en emulador/teléfono. La prueba PHP `AdminMobileApiTest` intentada en este entorno también quedó bloqueada por la revisión automática de una conexión auxiliar de telemetría externa; no se reporta como aprobada.

Pruebas manuales antes de aprobar:

1. Login administrativo correcto y credenciales incorrectas; cuenta cliente rechazada.
2. Cerrar y abrir la app: restaura la sesión sin guardar contraseña. Girar el teléfono durante una consulta: conserva la pantalla y no duplica la petición.
3. Comparar importes e ítems de un pedido con la web, incluidos extras/combos y descuentos. Probar lista vacía, filtros, búsqueda y varias páginas.
4. Sin Internet al abrir, actualizar y salir: mensaje de error y reintento; nunca inventar pedidos o confirmar una revocación fallida.
5. Revocar el token/cambiar contraseña o rol en el servidor: al actualizar o volver a la app debe solicitar login y limpiar los datos visibles.
6. Cerrar sesión conectado y reabrir: no debe restaurar la cuenta. Comprobar que no aparecen datos privados en capturas o recientes.
7. Probar tamaños de fuente grandes y pantallas estrechas; los detalles largos se desplazan verticalmente.

La API debe estar desplegada y sus pruebas aprobadas en una base de pruebas: `php artisan test --filter=AdminMobileApiTest`. La app no edita pedidos; el registro de avisos añade dos tablas. Ver FIREBASE.md.

## Archivos principales

- `Api.kt`: transporte HTTPS y contrato JSON; errores públicos sin cuerpos internos del servidor.
- `TokenVault.kt`: almacenamiento cifrado y expiración local.
- `AdminViewModel.kt`: sesión, navegación, filtros y carga de datos.
- `MainActivity.kt`: interfaz Compose.
- `ApiContractTest.kt`: contrato, rol, inyección de cabeceras, importes/paginación y errores.

Referencias oficiales: [AGP 9.3](https://developer.android.com/build/releases/agp-9-3-0-release-notes), [Kotlin integrado](https://developer.android.com/build/migrate-to-built-in-kotlin), [Compose Compiler](https://developer.android.com/jetpack/androidx/releases/compose-kotlin), [Android Keystore](https://developer.android.com/privacy-and-security/keystore), [hashes Gradle](https://gradle.org/release-checksums/).
