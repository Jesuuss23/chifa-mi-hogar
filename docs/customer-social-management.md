# Gestión de accesos de clientes

Mi cuenta permite desvincular Google o Facebook. Se muestra para identidades vinculadas aunque el proveedor esté desactivado. No requiere nuevas migraciones; utiliza password_version de la entrega de contraseñas.

## Regla de acceso alternativo

Se exige contraseña actual de Chifa Mi Hogar y confirmación explícita. Comprobarla demuestra que se conserva el acceso por correo y contraseña, incluso al quitar el único proveedor social. Tener un hash guardado no basta: las cuentas sociales nacen con contraseña aleatoria y deben establecer una por el flujo de correo antes de desvincular.

No se elimina usuario, perfil, pedidos ni otro proveedor. La operación solo utiliza el usuario autenticado y un proveedor permitido, con CSRF, límite de cinco solicitudes por minuto y bloqueo de fila del usuario durante comprobación/eliminación.

Se aumenta password_version y rota remember_token al eliminar una vinculación. Las otras sesiones web caducan al navegar; se mantiene la sesión actual y carrito. Se descartan los estados OAuth pendientes de la sesión actual. Las vinculaciones nuevas guardan la versión de acceso y la comprueban bajo bloqueo para impedir que una solicitud antigua restaure una identidad retirada.

Google/Facebook vuelven a comprobar que la identidad sigue vinculada después de obtener el bloqueo del usuario. Una identidad eliminada no se une de nuevo por coincidencia de correo: el cliente debe entrar con contraseña y vincularla explícitamente.

La desvinculación local no revoca autorizaciones en Google/Meta ni elimina cuentas externas. No constituye eliminación completa de datos personales. La pantalla explica este alcance. La recuperación por correo necesita el SMTP configurado según docs/customer-passwords.md.

## Validación

```bash
php artisan test --filter='CustomerSocialAccountTest|CustomerPasswordTest|CustomerGoogleLoginTest|CustomerFacebookLoginTest'
php artisan test
npm ci
npm run build
php artisan view:clear
```

CustomerSocialAccountTest cubre ambos proveedores, contraseña incorrecta, confirmación, cuenta solo social, acceso directo posterior, propiedad, roles, proveedor desconocido, proveedores desactivados, repetición, cierre de otra sesión, intentos de revinculación antiguos y límite de peticiones.

No se pudieron ejecutar PHP/Node por el fallo de preparación del sandbox del asistente. Revisión estática realizada; falta ejecutar pruebas y comprobar en dos navegadores. La prueba de versión antigua reproduce el estado de una solicitud previa; no es una prueba concurrente con PostgreSQL.

Manualmente: comprobar ambos proveedores, recuperar contraseña de una cuenta social, quitar su único proveedor, volver a entrar con correo/contraseña y comprobar que pedidos/dirección siguen visibles. En otro navegador con sesión anterior, navegar y confirmar que exige nuevo acceso.

Siguiente entrega: experiencia visual de acceso y Mi cuenta, manteniendo foto de perfil opcional para el cierre de esa sección.
