# Tema formal del cliente

Primera entrega de apariencia formal, según la paleta OKLCH proporcionada por el propietario. Se activa mediante data-theme="formal" en los cuatro documentos del cliente: layout de tienda, acceso, cuenta y listado independiente de combos. El layout común cubre catálogo, detalle de platos/combos, carrito, checkout, confirmación, seguimiento e información legal.

Se mantienen los 32 colores claros y los 32 oscuros aportados. Colores burdeos, fondos claros, tarjetas blancas, radio base 0.4rem y sombras proporcionadas. Los botones y enlaces con aspecto de botón tienen radio 0.75rem. Los campos y superficies con rounded-none se adaptan al radio formal; no se elimina el recorte circular de fotos de perfil.

CSS optativo en storefront-formal.css, importado al final de los estilos existentes. No se sustituyen las variables globales del panel ni de delivery. Se reutilizan los nombres semánticos de Tailwind sin redefiniciones circulares (por ejemplo --font-sans: var(--font-sans)). El espaciado actual de 0.25rem se conserva.

La fuente declarada es Manrope con alternativas de sistema; Instrument Serif y las familias mono quedan declaradas. No se incluyen archivos de fuente ni conexiones a Google Fonts: si no están instaladas, se utilizan las alternativas. Incorporar fuentes locales con su licencia queda pendiente para garantizar una tipografía idéntica entre dispositivos.

El tema oscuro está definido para la clase .dark, sin activación automática ni control nuevo. El modo claro es el activo por defecto. No se añadió todavía el modal/selector, previsualización ni temas estacionales; será la siguiente entrega tras revisar esta base visual. Esta entrega modifica apariencia, no redistribuye completamente el catálogo.

## Validación

Revisión estática del alcance de selectores y de las vistas que activan el tema. No se añadieron pruebas que repliquen constantes CSS. El entorno no permitió iniciar comandos, por lo que build, pruebas existentes y revisión en navegador quedan pendientes:

```bash
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Comprobar móvil y escritorio: catálogo, personalización, combos, carrito, checkout, cuenta y login/registro. Revisar botones de cantidad, enlaces de compra, foco de teclado y textos de error. Verificar que admin y repartidor conservan su apariencia. Revisar contraste del modo oscuro antes de ofrecerlo a clientes.

No hay migraciones, APIs nuevas ni cambios de URLs, contenido SEO, permisos o flujos. El color de interfaz del navegador en la tienda se ajusta al burdeos (#6a0a17). No se publican cambios en el servidor automáticamente: deben revisarse y fusionarse mediante la rama.
