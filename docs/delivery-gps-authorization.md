# Autorización GPS sobre el pedido bloqueado

LocationController comprobaba el rol, la asignación y el tipo de entrega sobre
el modelo obtenido por la ruta, antes de abrir la transacción. Dentro de ella
recargaba el pedido con lockForUpdate, pero no repetía esa autorización.

Si la asignación cambia entre ambas lecturas, el modelo inicial queda desactualizado.
Ahora iniciar, actualizar y detener GPS repiten authorizeOrder sobre la fila bloqueada,
antes de consultar el estado del seguimiento o modificar cualquier campo.

Se conserva la comprobación inicial, la validación de coordenadas/precisión,
los límites de frecuencia, los estados y las respuestas actuales.
No se añaden rutas, migraciones ni cambios de interfaz.

## Validación

```bash
php artisan test --filter=DeliveryGpsAuthorizationTest
php artisan test --filter=AdminOrderPaymentAndDispatchTest
php artisan test
```

15 casos contando proveedores de datos:
- Nueve escenarios de modelo obsoleto: tres acciones por reasignación,
  eliminación de asignación o cambio de modalidad.
- Tres peticiones HTTP de otro repartidor.
- Un ciclo autorizado de inicio, ubicación y detención.
- Dos estados finales que impiden actualizar coordenadas.

Los escenarios obsoletos reproducen de forma determinista las dos versiones de la fila;
no son una prueba multiproceso de concurrencia PostgreSQL.
Se verifica que el rechazo no modifica ningún atributo del pedido.
El cambio no altera el proceso administrativo de asignación.

Revisión estática realizada. PHP/PHPUnit no ejecutados por el bloqueo del entorno al
iniciar procesos. No se requiere migrate ni npm build.
