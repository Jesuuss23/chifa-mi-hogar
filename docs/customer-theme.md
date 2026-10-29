# Paleta del cliente — carrito, pago y cuenta

## Alcance

Se reutiliza el tema existente en carrito, checkout, confirmación de pedido, cuenta, inicio de sesión y registro.
Los colores de marca, superficies, textos y bordes usan tokens semánticos; radios rectos y sombras discretas.
.customer-surface aplica la misma pila de fuentes locales del catálogo sin cargar servicios externos.
La cuenta reutiliza la paginación del catálogo con una etiqueta accesible propia.

Se conservan el morado de Yape, los mensajes verdes de éxito y los avisos ámbar.
El visor QR conserva su fondo negro translúcido; las imágenes QR y el mapa no se recolorean.
Las clases dinámicas de selección de pago y estado de confirmación se adaptan junto con el HTML.

No se cambia la lógica de carrito, importes, vuelto, cotización, ubicación, registro, Turnstile, valoración ni consultas periódicas.
No se activa modo oscuro ni se añaden cookies, dependencias, APIs o migraciones.
La página independiente de seguimiento, las páginas legales y los paneles admin/delivery quedan para siguientes etapas.

## Verificación realizada

Comparación estática contra main:
- Iguales rutas, métodos, acciones, nombres, IDs, valores y atributos data-*.
- Iguales condiciones Blade, @csrf y serialización @json.
- Mismas fuentes externas de scripts (incluido Turnstile) y estilos inline del mapa.
- Código JavaScript fuera de cadenas de presentación idéntico.
- Expresiones de contenido conservadas; solo cambia la llamada a la vista de paginación de la cuenta.

No se agregan tests que solo comprueben nombres de clases CSS.
PHPUnit, Node, build de Vite y renderizado NO ejecutados por el bloqueo del sandbox local de Windows.

## Validación antes de fusionar

```bash
git fetch origin
git switch feat/customer-checkout-theme
npm ci
npm run build
php artisan view:clear
php artisan test
node --test tests/Frontend/order-tracking.test.cjs
```

No requiere migraciones. Si producción usa vistas precalculadas, regenerar con php artisan view:cache después de validar.

Revisión manual en móvil y escritorio:
1. Carrito vacío/con platos/con combos: editar cantidades y adicionales, guardar, eliminar y revisar totales.
2. Checkout invitado y autenticado: campos, errores, ubicación en mapa y cotización.
3. Efectivo: exacto y con vuelto; Yape: selección, número, copiar y abrir/cerrar QR.
4. Confirmación: importes, enlace de seguimiento y actualizaciones de estado.
5. Login y registro: validación, Turnstile si está habilitado y aviso de privacidad.
6. Cuenta: lista y paginación de pedidos, formulario de valoración y cierre de sesión.
7. Comprobar foco de teclado y ausencia de desbordamiento horizontal a 360 px.

Los estilos y las vistas deben desplegarse juntos; npm run build es necesario.
