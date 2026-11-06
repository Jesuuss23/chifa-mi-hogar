# Ayuda de comandos en el administrador

La página administrativa de WhatsApp presenta la guía antes de los formularios. Documenta los cuatro comandos que ya reconoce MetaWhatsappWebhookController:

| Comando | Requisito | Efecto |
| --- | --- | --- |
| ACTIVAR VENTANA | Número activo | Registra la interacción; no cambia accepting_orders. Recibir pedidos sigue siendo un permiso independiente. |
| ESTADO CHIFA | Activo + Controlar tienda | Consulta el estado e intenta responder. |
| ABRIR CHIFA | Activo + Controlar tienda | Habilita nuevos pedidos. |
| CERRAR CHIFA | Activo + Controlar tienda | Deshabilita nuevos pedidos, conservando catálogo y pedidos existentes. |

Se aclara el destino: escribir al número del negocio conectado a Meta desde el celular registrado. El identificador técnico no es el teléfono. El enlace wa.me existente conserva su destino al administrador y ahora se llama «Chatear con este administrador».

La ayuda explica el margen local de menos de 23 horas para avisos, los mensajes normales que actualizan la interacción sin entrar en el historial, y que el resultado open/closed es el estado de tienda después del comando, no un acuse de entrega. No promete reenvío inmediato de avisos pendientes.

Cambio solo de presentación y documentación: no modifica webhook, permisos, envío, reintentos, rutas ni APIs. No se enviaron mensajes reales. No se agregan botones que ejecuten comandos.

## Validación

Revisión estática contra MetaWhatsappWebhookController y OrderWhatsappNotificationService. No se añaden pruebas que dupliquen el contenido de una ayuda estática. Ejecutar las suites existentes:

```bash
php artisan test --filter=WhatsappCommandAuthorizationTest
php artisan test --filter=WhatsappInboundWindowTest
php artisan test --filter=OrderWhatsappRetryTest
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Revisión manual: abrir WhatsApp Meta como administrador; verificar las cuatro tarjetas en móvil y escritorio, navegar al desplegable con teclado y comprobar que el enlace al administrador conserva su destinatario. No es necesario enviar comandos para revisar esta entrega.

El entorno de edición sigue sin permitir iniciar la terminal por un error de aprovisionamiento del sandbox. Pruebas, compilación y revisión visual pendientes en el entorno del propietario. No requiere migraciones ni nuevas variables de entorno.
