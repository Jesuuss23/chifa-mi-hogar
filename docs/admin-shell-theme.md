# Identidad visual del menú y cabecera administrativos

Primer paso visual del panel: el layout administrativo reutiliza la paleta
crema/terracota existente mediante .storefront-theme, sin duplicar tokens.
Se ajustan menú lateral, cabecera, sesión y mensajes del layout.
El logo utiliza el mismo proveedor de datos y fallback que la tienda pública.

- Sección activa con color primario y aria-current="page".
- Menú móvil desplazable con enlaces que no se comprimen ni parten su texto.
- Tipografía de marca aplicada al menú y cabecera, sin cargar fuentes externas.
- Foco visible mediante las reglas compartidas del tema.
- Cerrar sesión conserva POST y CSRF; nombres de rutas y acciones intactos.
- Mensajes correctos conservan verde; errores usan el color destructivo.
- Robots noindex/nofollow conservado.
- No se activa modo oscuro.

Las vistas interiores (formularios, tablas y dashboard) mantienen sus estilos
propios y se adaptarán por módulo. Este cambio no completa todo el tema del admin.
Login de administración y portal delivery no se modifican.

## Validación

```bash
php artisan test
npm ci
npm run build
php artisan view:clear
```

Revisión estática: mismas rutas, método/action/CSRF de logout y directivas yield.
No se añaden pruebas que solo reflejen clases CSS.
PHP, build y revisión visual real pendientes por el bloqueo del entorno al
iniciar procesos. No requiere migrate ni configuración nueva.

Revisar en escritorio y a 360 px: logo/fallback, secciones activas, desplazamiento
del menú, foco por teclado, mensajes, acceso a formularios y cierre de sesión.
