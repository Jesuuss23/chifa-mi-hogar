# Recuperación de avisos de pedidos por WhatsApp

## Comportamiento

Se mantienen Meta, los mensajes de texto existentes y el margen de 23 horas
desde el último mensaje del administrador. No se añaden plantillas de pago.

Cada pedido/destinatario guarda attempts, next_attempt_at y attempt_token.
Máximo: tres intentos automáticos ante rechazos explícitos; espera de 60 segundos
tras el primero y 300 tras el segundo. El scheduler puede añadir hasta un minuto
de espera y la cola puede añadir su propio retraso.

- sent: Meta devolvió un ID de mensaje. No implica confirmación de entrega.
- pending: pendiente, incluida ventana no vigente. No consume intentos sin ventana.
- failed: rechazo explícito o configuración incompleta; reintentable dentro del límite.
- processing: un trabajador reclamó el envío.
- uncertain: timeout, error de servidor, respuesta sin ID, excepción o proceso
  interrumpido. No se reenvía automáticamente para evitar duplicados.

Un processing de cinco minutos pasa a uncertain. Un trabajador antiguo ya no
puede sobrescribir ese estado, porque perdió su token de intento.
No se promete entrega exactamente una vez: una caída después de que Meta acepte
el mensaje y antes de guardarlo localmente deja un resultado incierto.

Solo se recuperan pedidos no entregados/cancelados y creados en las últimas
24 horas, a administradores activos con receive_orders y ventana vigente.
Los avisos más antiguos se conservan para inspección y no se anuncian como nuevos.
El job vuelve a comprobar estas condiciones antes de enviar.

Las notificaciones failed anteriores a esta migración se marcan uncertain:
el código anterior no distinguía un rechazo de una aceptación seguida de timeout.
Los registros sent existentes no cambian.

## Despliegue

1. Aplicar la migración pendiente con el procedimiento habitual (php artisan migrate).
   Detener/pausar los trabajadores durante el cambio para no ejecutar código antiguo
   contra los nuevos estados. No usar migrate:fresh.
2. Mantener una cola persistente: QUEUE_CONNECTION=database y un caché compartido
   persistente (por ejemplo CACHE_STORE=database). No usar sync para producción.
3. El trabajador debe escuchar whatsapp-orders. Comando de referencia:
   php artisan queue:work --queue=whatsapp-orders --timeout=60 --tries=3
   Integrarlo en el supervisor existente; no lanzar otro trabajador sin revisar
   la configuración actual. retry_after debe ser mayor que el timeout; el valor
   database por defecto del repositorio es 90 segundos.
4. Mantener una única entrada del scheduler cada minuto (no duplicarla si ya existe):
   * * * * * cd /var/www/chifa-mi-hogar && php artisan schedule:run >> /dev/null 2>&1
5. Reiniciar los trabajadores mediante el supervisor/procedimiento habitual
   después de desplegar. El comando de recuperación está registrado en routes/console.php.

ShouldBeUnique reduce jobs duplicados para un mismo pedido mediante el caché.
El bloqueo de fila y el token de intento protegen cada destinatario.
Los reintentos de errores de infraestructura del job son independientes del
límite persistente de tres intentos HTTP por destinatario.

## Operación

php artisan whatsapp:retry-orders

Selecciona hasta 100 pedidos con avisos pendientes/reintentables y encola su envío.
Con la configuración correcta puede enviar mensajes reales: no usar para validar
pruebas de desarrollo con credenciales de producción.

php artisan whatsapp:retry-orders --review

Solo muestra los últimos 100 registros failed, processing o uncertain con sus
IDs, pedido y número de intentos; no encola, envía ni cambia estados.

Si un aviso está uncertain, comprobar primero el chat receptor y atender el pedido
desde administración. No cambiarlo masivamente a pending: puede haber sido recibido.
Los avisos agotados y los inciertos requieren revisión humana; este parche no añade
un botón de reenvío ni una autorización automática de más intentos.

## Pruebas

Con SQLite en memoria de phpunit.xml:

php artisan test --filter=OrderWhatsappRetryTest

Los 23 casos usan mocks/Http::fake y prohíben HTTP externo. No se envían mensajes reales.
Repetir las pruebas anteriores de checkout para comprobar el despacho de jobs.
Este entorno de revisión no dispone de PHP/Composer: ejecución PHPUnit pendiente.
