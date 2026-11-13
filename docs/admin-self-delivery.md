# Administrador como repartidor

El administrador conserva su rol y su sesión. El menú de Operación incorpora **Mis entregas**.

1. Abrir un pedido confirmado o en preparación.
2. En Asignar repartidor, elegir **Yo · nombre (administrador)** y guardar.
3. Mantener el flujo habitual para ponerlo **En camino**.
4. Entrar a Mis entregas para navegar, iniciar/detener el GPS y confirmar la entrega.
5. Si es efectivo pendiente, confirmar el cobro antes de finalizar. El cambio queda visible en administración.

En el panel de reparto solo aparecen los pedidos asignados al usuario actual. El administrador tampoco puede enviar GPS ni confirmar entregas ajenas desde esas rutas. Las comprobaciones de asignación bajo bloqueo se conservan. Los enlaces de regreso permiten volver a administración sin cerrar sesión. **Cerrar sesión** cierra la sesión compartida de ambos paneles.

El acceso directo por el formulario de repartidor también acepta cuentas administrativas, manteniendo los límites de intentos. Los clientes no pueden entrar. El selector permite repartidores y al propio administrador; no asigna otros administradores.

No hay migraciones, nuevas cuentas ni cambios de rol. La gestión de cuentas de repartidores permanece separada.

## Comprobación

```bash
php artisan optimize:clear
php artisan test --filter='AdminDeliveryAccessTest|DeliveryCompletionTest|DeliveryGpsAuthorizationTest|AdminOrderPaymentAndDispatchTest|AuthenticationRateLimitTest'
php artisan view:cache
```

AdminDeliveryAccessTest cubre acceso, autoasignación, aislamiento entre repartidores, validaciones de estado, GPS, cobro/entrega idempotente, reasignación concurrente y autenticación por rol.

Las pruebas no pudieron ejecutarse en el entorno de edición: la terminal Windows falló al iniciar el sandbox (helper_sandbox_lock_failed). Se entregan para ejecución en el servidor.
