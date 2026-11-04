# Contraseñas de clientes

Recuperación por correo y cambio desde Mi cuenta, también para cuentas creadas con Google/Facebook. No añade servicios de pago. Conserva los métodos sociales y los datos del cliente. La desvinculación de proveedores queda para otra entrega.

## Despliegue y pruebas

```bash
php artisan migrate
php artisan test --filter='CustomerPasswordTest|CustomerGoogleLoginTest|CustomerFacebookLoginTest'
php artisan test
npm ci
npm run build
php artisan config:cache
php artisan view:clear
```

La migración añade users.password_version (0). Desplegar la migración antes de atender peticiones con el nuevo código. No revertirla mientras esté activo el middleware. El entorno del asistente no pudo iniciar procesos por un error de preparación del sandbox; pruebas escritas, ejecución pendiente.

## Correo real

Configurar APP_URL=https://chifamihogar.com y el transporte disponible. El enlace utiliza APP_URL, no el Host recibido. No publicar una configuración con otro dominio.

Ejemplo genérico para un SMTP ya disponible (sustituir los valores):

```dotenv
APP_URL=https://chifamihogar.com
MAIL_MAILER=smtp
MAIL_SCHEME=smtp
MAIL_HOST=HOST_SMTP_DEL_PROVEEDOR
MAIL_PORT=587
MAIL_USERNAME=USUARIO_SMTP
MAIL_PASSWORD=SECRETO_SMTP
MAIL_FROM_ADDRESS=REMITENTE_AUTORIZADO
MAIL_FROM_NAME="Chifa Mi Hogar"
```

Utilizar puerto/esquema/TLS según el proveedor y no desactivar validación de certificados. Secretos solo en el .env del servidor. No se configura ni se contrata un servicio automáticamente. Comprobar entrega real y spam desde una cuenta controlada por el propietario.

Con log/array o transportes compuestos la solicitud devuelve 503 antes de generar un enlace: no guarda tokens utilizables en logs por la configuración predeterminada de Laravel. Se admiten transportes directos. Si se necesita failover, debe revisarse por separado que todos sus destinos entreguen correo real.

El envío es síncrono, no requiere un worker. Un fallo SMTP devuelve el mensaje genérico y registra una advertencia sin correo, token ni detalles del error; supervisarla. Puede ser necesario esperar el throttle del broker antes de solicitar de nuevo.

## Flujo y límites

- Login incorpora «Olvidé mi contraseña» y Mi cuenta permite solicitar un enlace para establecerla en cuentas sociales.
- Para abrir el enlace con sesión iniciada, cerrar sesión primero o usar un navegador sin sesión. El formulario avisa de ello.
- Se muestra el mismo mensaje al solicitar enlaces para correos desconocidos o de personal. Solo clientes reciben correo. No se garantiza tiempo de respuesta idéntico. Coincidencias ambiguas por mayúsculas no reciben enlace.
- Broker users de Laravel: hash de token almacenado, vigencia configurada actualmente en 60 minutos, espera de 60 segundos entre envíos y cinco POST por minuto/IP.
- Restablecimiento: token y correo exactos, contraseña confirmada y mínimo de ocho caracteres. Bloqueo de fila del usuario durante validación/consumo para serializar usos concurrentes.
- No inicia sesión automáticamente ni cambia rol, perfil o identidades sociales. Rota remember_token e incrementa password_version.
- Cambio desde Mi cuenta: exige contraseña actual, invalida enlaces pendientes, conserva sesión actual/carrito y cancela vinculaciones OAuth pendientes.
- Middleware web comprueba la versión de contraseña en cada petición del cliente. Las sesiones antiguas se cierran al navegar, también en páginas públicas, conservando el carrito. Las sesiones previas sin versión equivalen a 0.
- Nuevos logins directos/sociales registran la versión actual. No modifica sesiones de administrador/repartidor ni tokens API/Sanctum: alcance limitado al acceso web del cliente.
- Formularios de recuperación sin indexación, no-store y no-referrer. Evitar registrar URLs completas con tokens en proxies o instrumentación propia. No agregar servicios externos al formulario.

## Comprobación manual

Solicitar un enlace real, abrirlo sin sesión, cambiar contraseña y probar login con la nueva. Comprobar que el enlace usado ya no sirve. En dos navegadores, cambiarla en uno y verificar cierre del otro al navegar. Confirmar carrito, acceso social y vinculación con la contraseña recién establecida.

Referencias:
https://laravel.com/docs/12.x/passwords
https://laravel.com/docs/12.x/authentication

Siguiente paso: gestión de métodos de acceso y experiencia visual de registro/login/Mi cuenta. Foto opcional al cierre de Mi cuenta.
