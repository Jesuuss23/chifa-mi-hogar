# Aplicación Android: primera entrega

Estado: API de autenticación y consulta; todavía no hay APK ni pantallas Android.
Base: `https://chifamihogar.com/api/v1/admin`. Solo HTTPS en producción.
Reutiliza Laravel, Sanctum y la base de datos existentes. No añade proveedores de pago.

## Contrato v1

Enviar JSON con `Accept: application/json` y `Content-Type: application/json`.
Excepto login, enviar `Authorization: Bearer <access_token>`.

| Método | Ruta | Uso |
| --- | --- | --- |
| POST | /auth/login | email, password, device_name (máximo 80 caracteres) |
| GET | /auth/me | Identidad del administrador actual en data |
| POST | /auth/logout | Revoca únicamente el token utilizado; 204 |
| GET | /orders | Lista paginada: data, links y meta |
| GET | /orders/{id} | Detalle en data con datos del cliente, dirección e ítems históricos |

Login devuelve token_type, access_token, expires_at y user (id, name, email, role).
El token completo se entrega únicamente al iniciar sesión. No guardarlo en logs.
No se necesita registro nuevo: se utilizan las cuentas administrativas existentes.

Filtros de pedidos: status, search (código o nombre, máximo 100 caracteres),
per_page (1–50, predeterminado 20) y page (desde 1).
Estados: pending, confirmed, preparing, on_the_way, delivered, cancelled.
Orden: fecha de creación descendente, desempate por ID descendente.

Los importes son cadenas decimales, sin recalcular precios del catálogo.
Las fechas son ISO 8601. Cada línea del detalle contiene extras y combo_components
guardados en el pedido. No se entregan tokens de seguimiento/checkout,
contraseñas ni rutas de comprobantes. has_payment_receipt solo indica su existencia.
Los nombres y las notas son texto: Android debe renderizarlos como texto, no HTML.

## Sesiones y seguridad

- Tokens limitados a admin-mobile:read, con caducidad fija de 30 días, sin renovación automática.
- Máximo diez sesiones móviles por administrador. La undécima reemplaza la más antigua.
- Cambiar password o role mediante el modelo User revoca los tokens admin-mobile.
  Las actualizaciones SQL masivas de contraseñas deben revocarlos expresamente.
- El rol actual se comprueba en cada petición. Una sesión web no sustituye el token móvil.
- Login: cinco peticiones por minuto/IP, además del limitador compartido de fallos
  correo/IP (cinco fallos, ventana de quince minutos desde el primero).
- Rutas autenticadas: 120 peticiones por minuto. Respuestas privadas sin caché.
- 401: sesión ausente, revocada o caducada; volver al login.
  403: acceso no autorizado. 422: validación/credenciales/límite compartido.
  429: límite de peticiones; respetar Retry-After. 404: pedido inexistente.

La aplicación futura guardará el token cifrado con una clave protegida por Android
Keystore y lo excluirá de copias de seguridad. Cerrar sesión lo elimina del servidor
y del dispositivo. No se promete una sesión permanente.

## Validación antes de fusionar

No hay migraciones nuevas: se reutiliza personal_access_tokens.

```bash
php artisan optimize:clear
php artisan test --filter=AdminMobileApiTest
php artisan test
php artisan route:list --path=api/v1/admin
```

Pruebas preparadas para acceso por roles, tokens reales, expiración y revocación,
límites, paginación, datos históricos y exclusión de secretos.
No fueron ejecutadas por el agente: el entorno local no pudo iniciar comandos.
Revisar resultados en desarrollo antes de desplegar.

## Siguientes entregas

1. Validar y fusionar esta API.
2. Crear proyecto Android Studio con Kotlin y Compose: login, lista, filtros y detalle;
   estados de carga, errores, reintento y caducidad de sesión.
3. Añadir acciones administrativas reutilizando reglas existentes: confirmar,
   preparar, verificar cobros, asignar repartidor y despachar. Proteger reintentos y
   conflictos entre app y web; nunca actualizar estados solo en el teléfono.
4. Integrar panel delivery y entrega, respetando roles y cobros.
5. Añadir avisos de nuevos pedidos y permisos solicitados cuando sean necesarios.
6. Probar en teléfonos reales y entregar APK firmada para uso interno.
7. Después, desarrollar la experiencia de cliente sobre la misma API, con permisos separados.

Cada entrega tendrá su propia rama y pruebas. Mantener la web operativa durante el proceso.
