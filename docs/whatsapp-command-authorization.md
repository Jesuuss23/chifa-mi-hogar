# Permisos actuales antes de ejecutar comandos WhatsApp

El webhook comprobaba permisos antes de la transacción. Una revocación entre esa
lectura y la ejecución podía dejar un comando autorizado con datos obsoletos.

Ahora se vuelve a consultar el mismo registro por ID y teléfono con lockForUpdate,
dentro de la transacción y antes de crear el log o modificar StoreSetting.
Se requiere que siga activo y que mantenga can_control_store para ABRIR CHIFA,
CERRAR CHIFA y ESTADO CHIFA. ACTIVAR VENTANA sigue disponible a números activos
sin permiso de control.

Un registro eliminado, desactivado, con teléfono cambiado o sin el permiso
requerido no ejecuta el comando, no genera log de ejecución ni envía respuesta.
Se mantiene el HTTP 200 para un evento firmado que no debe ejecutarse.
La deduplicación por meta_message_id y los mensajes existentes se conservan.

El bloqueo ordena las operaciones: una revocación confirmada antes de adquirirlo
se respeta. No revierte un comando que ya haya sido autorizado y confirmado antes
de la revocación. La respuesta externa se envía después de terminar la transacción.

## Validación

```bash
php artisan test --filter=WhatsappCommandAuthorizationTest
php artisan test --filter=WhatsappInboundWindowTest
php artisan test --filter=OrderWhatsappRetryTest
php artisan test
```

12 casos: seis cambios entre lecturas, los cuatro comandos autorizados, activación
sin permiso de control y reenvío del mismo mensaje sin repetir acción/respuesta.
Los cambios entre lecturas usan un listener determinista; no son una prueba
multiproceso PostgreSQL. MetaWhatsappService está simulado: no hay envíos reales.

Revisión estática realizada. PHPUnit no ejecutado por el bloqueo conocido del entorno.
No requiere migrate, npm build ni nuevas variables de entorno.
