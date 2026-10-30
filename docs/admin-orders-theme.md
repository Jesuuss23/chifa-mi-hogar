# Identidad visual de la gestión de pedidos

El listado y el detalle de pedidos usan los tokens crema/terracota del layout:
tarjetas, tabla, filtros, enlaces, formularios y paginación. Los estilos de
controles se limitan a .admin-orders-surface y no afectan al resto de módulos.

- Se conserva verde para cobros/pagos verificados y ámbar para efectivo/pendientes.
- Errores y pago rechazado usan el color destructivo.
- Búsqueda y selectores tienen nombres accesibles; etiquetas del cambio de estado
  se vinculan a sus controles. La tabla incorpora caption y scope.
- La paginación reutiliza la vista existente con etiqueta Paginación de pedidos,
  manteniendo URLs, filtros y datos del paginador Laravel.
- El mensaje de éxito se muestra una sola vez desde layouts.admin; se retiran
  las copias duplicadas de ambas vistas.
- La cabecera distingue Pedidos y Detalle del pedido.
- Se mantienen todos los textos de operación, rutas, campos, métodos, CSRF,
  condiciones, estados, cálculos y acciones de cobro/asignación/despacho.

No cambia controladores ni modelos. No añade pruebas que solo comprueben clases CSS.

## Validación

```bash
php artisan test --filter=AdminOrderPaymentAndDispatchTest
php artisan test
npm ci
npm run build
php artisan view:clear
```

Revisión estática de rutas, campos, métodos y condiciones realizada.
PHP, compilación y revisión visual real pendientes por el bloqueo conocido
al iniciar procesos en el entorno. No se requiere migrate.

Revisar en móvil/escritorio: filtros, tabla desplazable, paginación, un pedido
con efectivo y otro con pago digital, asignación, cambio de estado, errores y
mensaje de éxito único. Las condiciones de disponibilidad de cada acción
siguen dependiendo del estado actual del pedido.
