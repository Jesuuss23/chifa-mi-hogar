# Salvia y renovación del administrador

## Activación
El panel y el acceso administrativo usan Salvia al desplegar esta rama.
La tienda conserva su tema guardado: activar Salvia en Administración → Apariencia
si se desea usar también allí. Delivery no cambia. No hay migraciones.

## Diseño
Paleta OKLCH aportada por el propietario, con radios de 1.4rem, controles redondeados,
sombras y espaciado .27rem limitados al tema, sin cambiar tokens globales.
Se incluyen los valores claros/oscuros; el oscuro queda preparado para una clase
.dark, sin activación automática ni nuevo interruptor.
Los enlaces y textos pequeños usan el acento oscuro o un gris más legible: el verde
primario claro se reserva para fondos con texto negro. El foco también usa el acento.
Los colores semánticos de estados de pedidos y errores se conservan.

Plus Jakarta Sans variable 200–800 se sirve localmente en WOFF2, Latin/Latin Extended,
con font-display: swap y licencia OFL incluida. Ambos archivos suman 49,076 bytes.
No hay peticiones a Google Fonts. Lora e IBM Plex Mono son nombres opcionales de
familia con alternativas locales; no se descargan al no ser necesarias para esta UI.

## Administración
- Inicio y grupos Operación / Carta / Negocio / Opiniones.
- Se conservan todos los destinos y formularios existentes, incluido logout POST/CSRF.
- Sidebar desplazable en escritorio; menú plegable nativo (sin JS) en móvil y escritorio.
- Encabezado claro, tarjetas, campos y modales redondeados.
- Accesos a pedidos, productos y clientes desde el resumen; indicadores sin cambios.
- Enlace para saltar al contenido, foco visible y marca de sección activa.

## Validación
Revisión estática de alcance CSS, navegación e imports. Los blobs de fuentes se
compararon con los SHA de origen. No se ejecutaron PHP/Vite/navegador porque el
entorno local no pudo iniciar el sandbox.

```sh
php artisan test --filter=SageThemeTest
php artisan test --filter=AdminDashboardTest
php artisan test --filter=AdminAppearanceTest
php artisan test --filter=AdminCustomerDirectoryTest
php artisan test --filter=PrivateWebResponsesTest
npm ci
npm run build
php artisan view:clear
```

Revisar en 320/375/1280 px: menú plegado/abierto, navegación con teclado, tablas,
pedidos y formularios de edición, modal Clientes y Apariencia, acceso y logout.
Revisar fuentes con tildes/ñ, y la tienda con Salvia y con otro tema.
La previsualización sigue siendo privada y no interactiva.
