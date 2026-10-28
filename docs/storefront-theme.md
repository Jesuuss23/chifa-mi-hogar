# Paleta del catálogo — primera etapa

## Cambios

- Fondo crema y acentos terracota con los valores OKLCH proporcionados por el negocio.
- Variables en resources/css/storefront-theme.css, importadas desde app.css (Tailwind 4).
- Variables limitadas a .storefront-theme, sin reemplazar el tema global del admin/delivery.
- Tipografía local Fira Code → JetBrains Mono → Menlo → monospace. Sin descargar fuentes externas; si no están instaladas, el navegador usa su monospace.
- Tipografía aplicada al encabezado, pie y catálogo mediante .catalog-surface.
- Bordes rectos (rounded-none) y sombras discretas en las vistas adaptadas.
- Clases semánticas también en las selecciones y resúmenes generados por el configurador.
- Foco visible en controles del storefront.
- Paginación de platos y combos con la misma paleta, aria-current y enlaces existentes de Laravel.
- Texto auxiliar usa foreground/75 para evitar depender del tono muted-foreground más tenue.

## Alcance incremental

Adaptados: encabezado y pie compartidos, portada/listado de platos, ficha/configurador de plato, listado y ficha de combos.

El cuerpo del layout compartido adopta fondo y texto del tema; los interiores de carrito, checkout, seguimiento, legales y cuenta conservan sus estilos actuales y quedan para la siguiente etapa.
Los formularios, campos, rutas, CSRF, reglas de precios y flujo de configuración no cambian.
Admin, delivery y autenticación no se rediseñan en esta rama.

La paleta oscura está declarada y preparada mediante .dark, pero no se activa automáticamente ni se incorpora un selector mientras quedan vistas por adaptar.
No se añade almacenamiento, cookies ni peticiones externas.

## Validación

Revisión estática realizada:
- Rutas, acciones, métodos, nombres/identificadores y atributos data-* iguales al original.
- Directivas CSRF conservadas.
- Código JavaScript fuera de cadenas de presentación sin cambios.
- Preservados los cálculos y expresiones Blade; solo cambian clases y la vista de paginación.
- Ningún controlador, servicio, modelo, API ni dependencia modificado.

No se ejecutó npm build, PHP ni revisión visual: el entorno Windows del asistente sigue bloqueado al aprovisionar el sandbox.
No se añaden pruebas de clases CSS que solo reproduzcan la implementación.

## Probar en servidor

```bash
git fetch origin
git switch feat/storefront-catalog-theme
npm ci
npm run build
php artisan view:clear
php artisan test
```

No requiere migraciones. Si producción utiliza vistas compiladas, regenerarlas con php artisan view:cache tras validar.

Revisión manual sugerida a 360 px, 768 px y escritorio:
1. Portada: encabezado, menú móvil, logo, categorías activas y paginación con más de 12 platos.
2. Ficha: cambiar cantidades, elegir recomendaciones, editar adicionales y copiar configuración entre platos.
3. Agregar al carrito y confirmar que cantidades, precios y selección se conservan.
4. Combos: listado con más de 12 registros, ficha, personalización y agregado al carrito.
5. Recorrer enlaces y botones con Tab: foco visible y sin desbordamiento horizontal.
6. Comprobar que el admin y los accesos de cliente/repartidor conservan su presentación.

Si aparecen estilos anteriores, recargar sin caché después de compilar. Los tokens CSS y las vistas deben desplegarse juntos.

Referencia de implementación: https://tailwindcss.com/docs/theme
