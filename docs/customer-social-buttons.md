# Botones sociales de acceso y registro

Se eliminan los párrafos repetidos debajo de cada proveedor y sus avisos duplicados. Se muestra una sola nota breve: «Usamos tu nombre y correo disponible, nunca tu contraseña del proveedor». El enlace Privacidad aparece cuando las páginas informativas están publicadas, siguiendo la condición existente.

Google: fondo blanco, borde gris y G multicolor. Facebook: fondo azul y f blanca. SVG integrados en Blade, decorativos para lectores de pantalla; los botones conservan nombres de texto, foco visible, altura mínima de 48 px y esquinas redondeadas. No hay descargas de logos, fuentes, scripts o SDK nuevos.

Se conservan los formularios POST, @csrf, rutas customer.google.start/customer.facebook.start, activación condicional y errores de cada proveedor. No cambia vinculación, permisos ni OAuth. Aplica a login y registro que comparten customer.auth.social.

## Verificación

Las URLs públicas no fueron accesibles desde la herramienta de navegación. Se revisaron los archivos del repositorio. No se pudieron ejecutar comandos ni abrir una previsualización local por el error de aprovisionamiento del sandbox.

```bash
php artisan test --filter=CustomerGoogleLoginTest
php artisan test --filter=CustomerFacebookLoginTest
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Verificar /iniciar-sesion y /registro en móvil/escritorio: ambos proveedores, solo uno y ninguno habilitado; errores visibles, navegación por teclado y retorno OAuth. No se añaden pruebas que repliquen texto estático. Sin migraciones ni dependencias.

Referencias de identidad visual:
- https://developers.google.com/identity/branding-guidelines
- https://www.meta.com/es-la/brand/resources/facebook/logo/
