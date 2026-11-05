# Presentación del acceso de clientes

Pantallas de login, registro y recuperación comparten estructura visual con el logo configurado, paleta existente y tipografía local. En móvil aparece primero el formulario; en escritorio se distribuyen formulario y beneficios en dos columnas. El acceso como invitado permanece visible.

Se mantienen rutas, nombres de campos, CSRF, límites del servidor, acceso social condicional y Turnstile del registro. No se añaden paquetes, fuentes remotas, scripts ni servicios. No se cambia Mi cuenta en esta rama.

Mejoras: navegación entre login/registro con aria-current, salto al formulario, título principal identificado, errores asociados a campos mediante aria-invalid/aria-describedby, explicación de contraseña mínima y perfil posterior, inputs de 16px y controles de al menos 48px. El tema y foco existentes siguen vigentes. Las pantallas comparten noindex,nofollow y no-referrer; los encabezados no-store del controlador de recuperación se conservan.

## Validación

```bash
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

No se pudieron ejecutar comandos ni abrir vista real por el fallo de preparación del sandbox del asistente. Se revisaron estáticamente formularios, rutas, directivas Blade e imports. No se añadieron pruebas que repliquen clases CSS. Ejecutar las suites existentes de autenticación y el build.

Revisar en navegador a 320px, 360px, 390px, 768px y escritorio; también zoom 200%, teclado y foco. Probar con ambos proveedores, uno y ninguno configurados, errores de formulario, conservación de nombre/correo y contraseñas siempre vacías. Probar registro con Turnstile real y errores de verificación. Turnstile utiliza el tamaño compact para evitar desbordamiento en formularios estrechos, conservando acción, idioma y validación. Comprobarlo con contenido real. Referencia oficial: https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/widget-configurations/. Confirmar logo existente/ausente y enlaces legales solo cuando estén publicados. Verificar que la recuperación conserva tokens y que el correo real sigue funcionando.

Esta rama no certifica contraste ni cumplimiento de accesibilidad: esas comprobaciones visuales y con lector de pantalla requieren la aplicación ejecutándose.

Siguiente: ordenar visualmente Mi cuenta (perfil, entrega, pedidos y seguridad), dejando foto opcional para el cierre.
