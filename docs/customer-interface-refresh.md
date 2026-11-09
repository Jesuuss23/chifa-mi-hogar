# Interfaz de clientes: navegación, acceso y promociones

## Alcance
Cabecera compacta con carrito y acceso; menú nativo details, operable sin JavaScript,
con cierre adicional por Escape, clic exterior o navegación. Pie de página con fondo
contrastante y grupos de carta, contacto y enlaces legales publicados.

Jerarquía visual aproximada 50/30/20 mediante los tokens actuales: fondo tranquilo,
superficies diferenciadas y acentos de marca. No se cambia el tema seleccionado.
Estilos limitados a customer-web/customer-auth; admin y repartidor mantienen sus estilos.

Registro y restablecimiento usan campos con etiqueta permanente, placeholder del mínimo
y estimación orientativa local de contraseña. Login conserva correo y contraseña actual:
sin medidor ni mínimo nuevo. El indicador no cambia las reglas del servidor, no consulta
servicios, no persiste valores y no garantiza seguridad. Botones mostrar/ocultar aparecen
solo si JavaScript carga. Se conservan CSRF, Turnstile, errores y aviso de datos.

Los combos muestran “En oferta” solo si su precio es menor que la suma del catálogo.
Solo la primera oferta visible tiene emoji/borde animados; duran 3/4,5 segundos y
respetan prefers-reduced-motion. No se inventan descuentos, precios anteriores ni urgencia.

## Validación pendiente en servidor
```bash
php artisan optimize:clear
php artisan test --filter='CustomerInterfaceTest|CustomerPasswordTest|RegistrationTurnstileTest|CustomerCheckoutReturnTest|StorefrontComboCarouselTest|ComboAvailabilityTest|StorefrontSeoTest|LegalPagesTest|SageThemeTest'
node --test tests/Frontend/customer-interface.test.cjs tests/Frontend/category-strip.test.cjs tests/Frontend/combo-carousel.test.cjs
npm run build
php artisan view:cache
```

## Revisión visual/manual
- 320, 390, 768, 1280 y 1920 px: sin desbordamiento horizontal, zoom 200%, nombres largos.
- Original, formal, salvia, verano y Navidad: legibilidad, selección de categoría y botones.
- Móvil: menú, teclado de correo, carrito, dos tarjetas desde 390 px y pie apilado.
- Teclado: salto al contenido, Tab/Shift+Tab, Escape y foco visible.
- Registro/reset: placeholder, medidor, mostrar/ocultar, autofill y errores reales.
- Google/Facebook y Turnstile: comprobar con credenciales reales en dominio autorizado.
- Combo sin ahorro: sin distintivo; con ahorro: precio por separado y oferta;
  movimiento reducido: sin animación.
- Legal: conservar acceso a políticas y preferencias disponibles desde Cookies.

Se realizaron comprobaciones de sintaxis JS y siete escenarios con DOM simulado en V8.
No se ejecutaron PHPUnit, Vite, el runner Node ni captura visual: el entorno local no
pudo iniciar procesos por un fallo de aprovisionamiento del sandbox.
