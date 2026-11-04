# Acceso de clientes con Facebook

Esta entrega añade Facebook al registro directo y Google. Está desactivado por defecto; no instala dependencias ni crea migraciones. Requiere la tabla customer_social_accounts incorporada en la entrega de Google.

## Configuración

Crear/configurar una aplicación con Facebook Login en Meta y consultar en su panel los permisos y requisitos de publicación aplicables. No usar el token de WhatsApp como secreto de login. Configurar la URI de redirección exacta:

https://chifamihogar.com/auth/facebook/callback

Configurar en el .env del servidor (nunca subir secretos al repositorio):

```dotenv
FACEBOOK_LOGIN_ENABLED=true
FACEBOOK_LOGIN_APP_ID=ID_DE_LA_APP
FACEBOOK_LOGIN_APP_SECRET=SECRETO_DE_LA_APP
FACEBOOK_LOGIN_REDIRECT_URI=https://chifamihogar.com/auth/facebook/callback
FACEBOOK_LOGIN_GRAPH_VERSION=VERSION_COMPATIBLE_DE_TU_PANEL
```

Sustituir la versión por una versión soportada de Graph API con formato vNN.0. El valor v24.0 de las pruebas es una fixture, no una recomendación de versión actual. La aplicación oculta el botón si falta configuración o el formato es inválido; esto no comprueba que Meta acepte las credenciales o la versión.

Después de configurar:

```bash
php artisan config:cache
php artisan view:clear
```

Mantener HTTPS, cookies de sesión seguras y SameSite=lax para el retorno desde Meta. El caché debe persistir entre peticiones y ser compartido entre servidores: evita reutilizar estados OAuth. No usar array en producción.

En modo desarrollo probar con cuentas autorizadas en el panel Meta. Antes de abrir a clientes comprobar el acceso público y los requisitos de permisos/revisión que muestre el panel. Probar autorización, cancelación, retorno y cuenta sin correo en navegador real. Las pruebas simuladas no certifican la configuración de Meta.

## Privacidad y eliminación

Publicar los datos legales reales y verificar que /privacidad es accesible. La publicación sigue sujeta a los controles legales existentes; no introducir un RUC ficticio para habilitarla.

Configurar como política de privacidad https://chifamihogar.com/privacidad y, donde Meta permita instrucciones de eliminación, https://chifamihogar.com/privacidad#eliminar-datos-facebook.

La sección indica cómo solicitar eliminación por correo. Es un procedimiento manual que el negocio debe atender: verificar identidad, confirmar alcance y conservar un método alternativo antes de desvincular. Esta entrega no implementa un callback automático de eliminación ni permite asumir que quitar la app en Facebook elimina la cuenta local. Comprobar que la modalidad de instrucciones es aceptada para la aplicación en su panel.

Solo se solicitan public_profile y email y se recuperan id, name, email. No se consultan fotos, contactos o publicaciones ni se persisten tokens. La foto opcional del perfil se implementará al cerrar Mi cuenta.

## Comportamiento y protección

- Estado OAuth de un uso, vinculado a sesión/usuario y con caducidad de diez minutos; rutas POST con CSRF y límites de peticiones.
- Intercambio de código en servidor, inspección del token para comprobar aplicación, usuario y vigencia; /me debe devolver el mismo identificador. Se envía appsecret_proof.
- Una identidad ya vinculada inicia sesión aunque Facebook deje de compartir correo; no sobrescribe nombre, correo ni dirección locales.
- Una identidad nueva necesita un correo válido. Si no se comparte, se ofrece Google o registro directo.
- Nunca se unen cuentas automáticamente por correo. Vincular exige sesión de cliente, contraseña actual y el mismo correo para una identidad nueva.
- Las cuentas nuevas tienen rol customer y contraseña aleatoria; Facebook no marca el correo local como verificado. No se admite acceso de administradores/repartidores por este flujo.
- Las cuentas creadas exclusivamente por Google/Facebook todavía no tienen una contraseña conocida para vincular otro proveedor. La siguiente entrega implementará recuperación/establecimiento seguro de contraseña.
- No cambiar de App ID después de vincular usuarios sin planificar una migración: los identificadores de Facebook dependen de la aplicación.
- No registrar cabeceras Authorization, URLs completas ni parámetros de las peticiones OAuth. El intercambio GET contiene el secreto y código; debug_token contiene el token inspeccionado. Revisar cualquier instrumentación HTTP propia. Las respuestas de error locales no muestran estos datos.

## Validación

```bash
php artisan test --filter='CustomerFacebookLoginTest|CustomerGoogleLoginTest'
php artisan test
npm ci
npm run build
```

La suite cubre configuración, estados inválidos/repetidos, errores de red/proveedor, metadatos de token, identidad, colisiones de correo, roles, vinculación y conservación del carrito. Ejecutar también las pruebas existentes de Google y una prueba real de ambos proveedores y registro directo.

No fue posible ejecutar PHP/Node en el entorno del asistente por un fallo de preparación del sandbox; revisión estática realizada, ejecución pendiente del propietario.

## Referencias y límite de verificación

Se consultó el flujo OAuth del SDK oficial archivado de Meta:
https://github.com/facebookarchive/php-graph-sdk/blob/5.x/src/Facebook/Authentication/OAuth2Client.php

Las páginas actuales de documentación de Meta devolvieron HTTP 429/no estuvieron disponibles durante esta revisión. Confirmar versión, permisos y requisitos de publicación en el panel antes de activar; el SDK archivado no acredita requisitos actuales:
https://developers.facebook.com/docs/facebook-login/
https://developers.facebook.com/docs/graph-api/reference/debug_token/
https://developers.facebook.com/docs/development/create-an-app/app-dashboard/data-deletion-callback/
