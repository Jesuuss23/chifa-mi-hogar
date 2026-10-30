# Dashboard con datos reales

El dashboard mostraba valores constantes y un aviso de módulo sin implementar. Ahora consulta pedidos y configuración actuales, y usa el tema administrativo compartido.

## Definiciones

- Pedidos de hoy: creados durante el día calendario de Lima, incluidos los cancelados.
- Ventas completadas hoy: suma de total (incluye delivery y descuentos ya aplicados) de pedidos entregados hoy, con estado delivered y payment_status verified. Se usa delivered_at, no created_at ni payment_verified_at. No es un informe contable ni un resumen de cobros.
- Pedidos pendientes: estado pending, de cualquier fecha; no incluye preparación ni reparto.
- Cocina: accepting_orders de la configuración actual.
- Últimos pedidos: cinco, ordenados por created_at descendente y luego id descendente. Fechas mostradas en Lima.

Los límites del día se convierten a la zona de almacenamiento de la aplicación (config/app.php: UTC). No cambia la zona global ni los datos históricos. Las consultas usan inicio inclusivo y siguiente medianoche exclusiva. Solo lectura de pedidos; las transiciones y pagos conservan su comportamiento.

## Validación

Se agregan 8 casos de AdminDashboardTest: vacío y estado de cocina, límites del día de Lima, ventas por fecha de entrega/pago/estado, pendientes de otras fechas, cinco recientes ordenados con escape HTML, dos roles no autorizados e invitado. Las fechas de los fixtures se asignan explícitamente con forceFill porque created_at no es fillable.

Revisión estática realizada. PHP, Vite y navegador no ejecutados en el entorno de trabajo.

```sh
php artisan test --filter=AdminDashboardTest
php artisan test
npm ci
npm run build
php artisan view:clear
```

No requiere migraciones. Revisar el dashboard en móvil/escritorio, abrir el detalle de un pedido y contrastar indicadores con los datos del negocio. Cambiar apertura desde configuración/WhatsApp y recargar para comprobar el estado.
