# Paleta en seguimiento y páginas legales

Esta etapa adapta la página independiente de seguimiento y las vistas legales al tema ya presente en catálogo, carrito y cuenta.

- Seguimiento: superficies crema, acentos de marca, tipografía local, bordes rectos y sombras discretas.
- Clases de estados generadas por JavaScript actualizadas junto con el HTML.
- Se mantienen verdes de progreso y el color destructivo para cancelación; texto e iconos siguen indicando el estado.
- El mapa conserva sus imágenes, marcadores, color de ruta y tamaño.
- Aviso legal, privacidad y cookies comparten la tipografía y colores; tablas con bordes de la paleta y desbordamiento horizontal existente.
- El aviso de recogida de datos de registro/checkout también usa el texto semántico.

No se modifican textos legales, enlaces, datos, publicación/preview, cookies ni consentimientos.
No se modifica auth, rutas, cabeceras, token privado, metadatos noindex/referrer, proveedores, APIs o consultas del mapa.
No se activa el modo oscuro ni se rediseñan administración/repartidor.

## Validación

Comparación estática: atributos funcionales, salidas y condiciones Blade intactos; contenido legal idéntico al quitar atributos class.
Lógica JavaScript fuera de cadenas de presentación idéntica.
Los 11 casos existentes de order-tracking.test.cjs pasaron en una simulación V8 con adaptadores de DOM/temporizadores/AbortController y APIs de Node. No se cambiaron las pruebas.
Esta simulación no equivale a ejecutar Node, PHP, Leaflet o navegador real.
PHPUnit, ejecutor Node, build y revisión visual pendientes por bloqueo del sandbox local.

## Probar

```bash
git fetch origin
git switch feat/tracking-legal-theme
npm ci
npm run build
php artisan view:clear
php artisan test --filter='OrderTrackingPrivacyTest|LegalPagesTest|StorefrontSeoTest'
node --test tests/Frontend/order-tracking.test.cjs tests/Frontend/customer-profile.test.cjs
php artisan test
```

No requiere migraciones.

Revisar en móvil y escritorio:
1. Seguimiento recibido, en preparación, en camino con mapa, entregado y cancelado.
2. Cambio de estado sin recargar, estados de pago y retorno a la tienda.
3. Aviso legal, privacidad y cookies publicadas; previsualización administrativa si aún son borrador.
4. Tabla de cookies en pantalla estrecha y enlaces de navegación legal.
5. Aviso de datos en registro/checkout.
