# Ventas rápidas

## Uso

Admin → Operación → Ventas rápidas → Nueva venta.

1. Seleccionar platos, combos o adicionales y pulsar Agregar.
2. Ajustar cantidades con −/+; cada adicional tiene su propia cantidad total.
3. Revisar el total automático o escribir el total cobrado. No se solicita motivo.
4. Nombre opcional, Efectivo o Yape, Guardar venta.

La fecha predeterminada es hoy en Lima. El apartado plegable Fecha de venta
permite registrar ventas anteriores (desde 2000), nunca futuras.
Al guardar aparece el detalle y un botón para registrar otra venta.

Se usan platos, combos y adicionales no eliminados del catálogo, aunque estén temporalmente
desactivados online: este registro también sirve para ventas pasadas o presenciales.
Los adicionales son líneas independientes y no requieren vinculación a un plato.
Los combos se registran como una unidad con su precio de catálogo: solo cambia
su cantidad, no sus componentes. Se permite registrar un combo fuera de su
calendario online porque el módulo también registra ventas pasadas. Los adicionales
que se agreguen son líneas separadas, no una modificación de lo incluido en el combo.

## Reportes

Seleccionar Día, Mes o Año con una fecha de referencia, o un rango personalizado.
Filtrar por Efectivo/Yape y nombre. Los importes muestran todas las ventas que
coinciden con los filtros, no solo la página visible. El resumen puede agruparse
por día, mes o año; ambos listados tienen paginación. El rango máximo es diez años.

Los reportes incluyen únicamente ventas rápidas. No se suman a los pedidos web
ni al dashboard existente para evitar confundir fuentes o contar dos veces una venta.
Los importes son ventas cobradas, no ganancia. El registro no verifica un pago
con Yape: el administrador declara el medio con el que recibió el dinero.

## Registro y seguridad

- Acceso exclusivo de administradores, CSRF y límite de 30 guardados/minuto.
- UUID único por formulario, transacción y bloqueo del registro StoreSetting.
  Reintentar el mismo formulario devuelve la misma venta; con otros datos o
  con otro administrador responde 409 y no modifica el registro original.
- El botón se bloquea durante el envío. Si el navegador detecta falta de internet,
  conserva el formulario sin enviarlo ni reintentarlo automáticamente.
- Si la respuesta se pierde después de guardar, el mismo UUID permite reintentar.
  Una nueva venta tiene otro UUID: revisar Registros antes de crear otra si se
  cerró la pestaña y no se conoce el resultado. No hay almacenamiento offline.
- Entre 1 y 50 líneas; cantidades 1–99. Dinero calculado en céntimos; total
  entre S/0 y S/99,999.99, sin negativos, exponentes ni más de dos decimales.
- Precios base y nombres se leen del servidor; el total manual es una facultad
  del administrador. Se guardan ambos importes sin pedir explicación.
- Guarda una copia de nombres, cantidades y precios: cambiar/eliminar un plato
  después no altera el historial. Conserva autor y hora del registro.
- Fecha comercial DATE en Lima, separada de los timestamps de creación.
- No crea pedidos, despachos, notificaciones, cupones ni referidos. No afecta stock.
- El total se puede editar antes de guardar. No incluye edición ni eliminación
  de ventas guardadas en esta entrega.

## Publicación

```bash
composer install
php artisan migrate
php artisan optimize:clear
php artisan test --filter=QuickSaleTest
node --test tests/Frontend/quick-sales.test.cjs
npm ci
npm run build
php artisan view:cache
```

Requiere migrar antes de abrir el módulo. La migración crea únicamente quick_sales.
No usar migrate:rollback sobre ventas reales: elimina los registros de esta tabla.

Las pruebas cubren total manual, importes exactos, fechas y límites de período,
paginación, autorización, CSRF, datos manipulados, reintentos y snapshots históricos.
Las pruebas de doble envío son secuenciales: no sustituyen una prueba concurrente
real de PostgreSQL o un corte físico de conexión en un dispositivo.

Validación de la ampliación con combos: 14 pruebas del módulo (130 aserciones)
sobre PHP 8.3/SQLite y siete pruebas JavaScript aprobadas; caché Blade correcta.
La entrega base también pasó las pruebas de regresión y el build Vite. El build se
ejecutó mediante la API Vite con preserveSymlinks por restricciones locales de
Windows, sin cambiar las dependencias ni la configuración del proyecto.
No se realizó inspección visual en navegador ni ejecución contra PostgreSQL.
