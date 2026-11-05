# Conexión y vigencia del GPS en seguimiento privado

El seguimiento privado utiliza el mismo controlador de consultas de estado que la confirmación: fallos visibles, botón de actualización, timeout de 10 segundos, espera de 15 segundos tras éxito y 30/60 tras fallo. Pausa al ocultarse o desconectarse; termina al entregar/cancelar o perder acceso al enlace. Conserva el último estado válido.

El mapa mantiene Leaflet, OpenStreetMap y el endpoint de ruta existentes. La consulta GPS ahora tiene timeout de 10 segundos. El marcador del repartidor y la ruta se retiran cuando falla la consulta, se pierde conexión, se oculta la pestaña, termina el pedido o caduca la ubicación. El destino puede permanecer en el mapa; no representa una posición del repartidor.

Se validan coordenadas numéricas y sus rangos antes de enviarlas a Leaflet. Se exige GPS activo/reciente y location_updated_at válido. Cada posición caduca al completar 60 segundos desde ese instante, sin esperar otra respuesta del servidor. Un reloj del dispositivo adelantado/atrasado puede causar que se oculte el punto por precaución: no se prolonga la vigencia de fechas futuras. Al llegar una ubicación válida, se vuelve a dibujar.

Cada invalidación descarta respuestas de consultas GPS anteriores, evitando que reaparezca un marcador después de cancelar, desconectarse o cambiar de pestaña. Se limpia el temporizador anterior al renovar la posición. Se conserva la lógica del recorrido restante y sus APIs gratuitas.

## Validación

Ocho pruebas GPS y las once existentes del controlador de consultas pasaron en V8 con red, DOM y reloj simulados. También se comprobó la sintaxis del JavaScript integrado sustituyendo las expresiones Blade por valores de prueba. No equivale a compilar Blade ni revisar Leaflet en navegador.

Los comandos Node, PHPUnit y compilación no pudieron ejecutarse porque Windows bloqueó el arranque de procesos. Ejecutar antes de fusionar:

```bash
node --test tests/Frontend/tracking-gps-guard.test.cjs tests/Frontend/confirmation-polling.test.cjs
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Sin migraciones ni dependencias. Comprobar manualmente en móvil: pedido en camino con GPS activo, pérdida de red, señal que supera 60 segundos, ocultar/volver a la pestaña, ubicación recuperada y pedido entregado/cancelado. Confirmar que el marcador desaparece y vuelve solo con datos válidos. Verificar también la confirmación del pedido tras compartir su controlador de consultas.

Pendiente: revisión visual móvil real y validación operativa de estados, reseñas y GPS; después, cierre público de marca/SEO y datos legales reales conforme al plan.
