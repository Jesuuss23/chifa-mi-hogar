# Envíos simultáneos de valoraciones

La transacción del controlador comprobaba rating()->exists() sin bloquear el
pedido. Dos peticiones podían observar que no había valoración y competir en el
INSERT: el índice único impedía el duplicado, pero una petición podía fallar
por la restricción de base de datos.

La consulta del pedido, filtrada por el usuario autenticado, ahora usa
lockForUpdate antes de validar estado y existencia de la valoración. Dos envíos
a este controlador para el mismo pedido quedan serializados en PostgreSQL:
el segundo comprueba la valoración ya guardada y recibe el error de validación
existente. Se permiten tres intentos de transacción ante deadlocks.

Se mantienen:
- Valoraciones solo del cliente propietario y de pedidos entregados.
- Una valoración por pedido, respaldada por el índice único existente.
- Puntuación entera de 1 a 5 y comentario de hasta 1000 caracteres.
- La primera valoración no se sobrescribe en un reintento.
- El enlace externo a Google Reseñas es independiente de estas valoraciones.

No hay migraciones, cambios de frontend ni cambios de API.

## Validación

```bash
php artisan test --filter=CustomerOrderRatingTest
php artisan test
```

15 casos contando proveedores de datos: guardado y reintento, cinco estados no
entregados, pedidos de otro cliente/invitado, tres roles no autorizados y cuatro
entradas inválidas. El formulario no puede elegir user_id ni order_id.

Estas pruebas funcionales usan la configuración SQLite de la suite. No son una
prueba concurrente PostgreSQL y no demuestran por sí solas el comportamiento del
bloqueo bajo dos procesos. La garantía de exclusión se apoya en bloquear la fila
del pedido antes de consultar e insertar dentro de la misma transacción.
Todos los escritores de valoraciones deben respetar ese bloqueo.

Revisión estática realizada. PHPUnit no ejecutado en el entorno del asistente
por el bloqueo conocido de ejecución. No requiere migrate ni npm build.
