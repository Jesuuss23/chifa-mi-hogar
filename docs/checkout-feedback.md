# Errores y cotización en checkout

El resumen de errores recibe foco al cargar y ofrece enlaces a los controles conocidos. Los errores de coordenadas llevan al botón «Elegir en el mapa». Los errores generales conservan texto escapado sin construir enlaces con claves arbitrarias. Si un campo de pago está desactivado u oculto, el enlace lleva a la sección de pago.

Cuando se cambia dirección, ubicación, método de pago o datos de efectivo, se ocultan los importes de la cotización anterior y se muestra un aviso para recalcular. La restauración inicial del mapa no invalida la cotización. Cambiar nombre, teléfono o notas no la invalida. El aviso es de presentación: el servidor sigue validando y recalculando al confirmar; no se introduce un requisito nuevo de cotización ni se modifica el flujo de compra.

Sin JavaScript se mantienen los enlaces, el resumen y la advertencia original de recalcular. Requiere revisión visual en móvil y teclado.

## Pruebas

Se ejecutaron siete simulaciones V8 del bloque JavaScript real: foco del resumen/enlace, edición de contacto, edición de dirección, cambio de pago, ausencia de cotización, control desactivado e invalidación repetida del mapa. Todas pasaron. No equivalen a una prueba completa de navegador.

Se incluyen siete casos Node y tres casos PHPUnit. No se pudieron ejecutar esos comandos ni compilar Blade/Vite porque el entorno Windows bloqueó el arranque de procesos.

```bash
node --test tests/Frontend/checkout-feedback.test.cjs tests/Frontend/checkout-auth-draft.test.cjs
php artisan test --filter='CheckoutFeedbackTest|CheckoutAuthDraftTest|CustomerCheckoutReturnTest|CustomerProfileTest'
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

No requiere migraciones ni dependencias. Probar: provocar un error, navegar al campo desde el resumen, corregirlo; cotizar y cambiar dirección, punto del mapa y método de pago; comprobar aviso y volver a cotizar. Verificar que el borrador al identificarse sigue funcionando.
