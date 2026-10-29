# Última interacción WhatsApp sin retrocesos

El webhook ya comprobaba la firma HMAC del cuerpo y el phone_number_id configurado.
La fecha last_inbound_at se comparaba con el modelo leído y después se guardaba
sin condición. Dos peticiones podían leer la misma fecha y guardar primero el
mensaje más reciente y después uno más antiguo, acortando la ventana local.

Ahora la escritura incluye en SQL estas condiciones:
- El número sigue activo.
- last_inbound_at es NULL o anterior al timestamp recibido.

Se conserva la validación previa y la tolerancia actual de dos minutos para
fechas futuras. No se reinicia la ventana a la hora del servidor ni se cambian
comandos, respuestas, APIs o reintentos de notificaciones.

## Pruebas

```bash
php artisan test --filter=WhatsappInboundWindowTest
php artisan test --filter=OrderWhatsappRetryTest
php artisan test
```

13 casos contando proveedores: cuatro secuencias de fechas, dos intercalados
(fecha avanzada por otro proceso y desactivación), tres timestamps inválidos,
dos firmas inválidas, phone_number_id ajeno y número inactivo.

Los intercalados usan un listener después del SELECT para reproducir un modelo
obsoleto de forma determinista. No constituyen una prueba multiproceso PostgreSQL.
Las pruebas no envían mensajes reales: MetaWhatsappService está simulado.

La condición is_active protege esta escritura de fecha; no pretende resolver
en este cambio la revocación concurrente de permisos de comandos.

Revisión estática realizada. PHPUnit no ejecutado por el bloqueo conocido al
iniciar procesos. No requiere migrate, npm build ni nuevas variables de entorno.
