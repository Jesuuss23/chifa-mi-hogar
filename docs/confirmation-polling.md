# Conexión y actualización en la confirmación del pedido

La confirmación ahora indica cuando no puede consultar el estado y permite actualizar manualmente. Conserva el último estado válido ante errores HTTP, desconexión, timeout o JSON inesperado.

Consulta el endpoint privado existente del pedido, usando su tracking_token. Esto evita que crear otra compra en una pestaña cambie el pedido consultado desde una confirmación anterior. No incorpora proveedores ni APIs nuevas.

Solo hay una petición en curso. Timeout de 10 segundos; siguiente consulta a los 15 segundos tras el éxito o 30/60 segundos tras fallos. HTTP 429 también aplica esta espera; un nuevo fallo mantiene el máximo de 60 segundos. No consulta mientras la pestaña está oculta o el navegador indica desconexión; al volver se reanuda. El botón manual conserva el bloqueo de peticiones simultáneas.

Entregado/cancelado detienen consultas; 403/404/410 indican que el enlace ya no está disponible y también detienen reintentos. No altera pedidos ni pagos. El pago digital cancelado deja de solicitar comprobante. Al salir se aborta la petición y se ignoran resultados tardíos. Volver desde la caché de navegación recarga el estado del servidor.

Sin JavaScript se mantiene la información renderizada y un aviso para recargar o abrir seguimiento. El botón manual permanece oculto hasta iniciar JavaScript.

Esta entrega afecta solo a la pantalla de confirmación. La vista privada con mapa conserva su implementación; queda pendiente revisar su comunicación de fallos de estado/GPS y su uso móvil.

## Validación

11 pruebas del archivo JavaScript real ejecutadas mediante V8 con reloj, red y DOM simulados: todas aprobadas. La misma suite se entrega para Node. No es una comprobación visual de navegador. Node/PHPUnit/Blade/Vite no ejecutados porque Windows bloqueó el arranque de comandos.

```bash
node --test tests/Frontend/confirmation-polling.test.cjs
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Sin migraciones ni dependencias. Prueba manual: abrir una confirmación, desactivar red, reconectar y actualizar; cambiar estado desde administración hasta entregado/cancelado; verificar que no sigue consultando. Abrir otra compra en otra pestaña y confirmar que la primera conserva el seguimiento de su pedido. Probar cambiar de pestaña y volver desde el historial.
