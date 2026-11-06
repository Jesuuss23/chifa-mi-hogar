# Acceso administrativo

La página /admin/login reutiliza la paleta del negocio y el logo configurado en el panel. Tiene diseño adaptable, campos de al menos 48 px de alto, texto de entrada de 16 px, enlaces con foco visible y un enlace para saltar al formulario. Si no hay logo se muestra el nombre del negocio.

Los errores de correo y contraseña están asociados a sus campos mediante aria-describedby y aria-invalid, además del resumen de errores. Se conserva el correo ingresado y nunca se rellena la contraseña. Autocompletado username/current-password para gestores de contraseñas.

Se mantienen el POST admin.login.submit, CSRF, validación de rol, regeneración de sesión, redirección al panel y limitación de intentos. No se agregan accesos sociales ni registro administrativo. La página conserva noindex y añade no-referrer en el HTML.

## Validación

Cambio de presentación: no se añaden pruebas que repliquen el HTML. Ejecutar las suites existentes de autenticación y privacidad, compilar CSS y comprobar Blade:

```bash
php artisan test --filter=AuthenticationRateLimitTest
php artisan test --filter=PrivateWebResponsesTest
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Revisión manual:
- /admin/login a 320 px y en escritorio; con logo y sin logo.
- Navegación por Tab y enlace «Ir al formulario».
- Credenciales incorrectas: correo conservado, contraseña vacía, error visible y asociado al campo.
- Cuenta administrativa válida entra al panel; una cuenta cliente no entra.
- Comprobar zoom al 200 % y autofill del navegador.

El entorno de edición no permitió iniciar la terminal (fallo de aprovisionamiento del sandbox). No se ejecutaron pruebas, compilación ni revisión visual en navegador; quedan pendientes en el entorno del propietario. No requiere migraciones ni dependencias nuevas.
