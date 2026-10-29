# Aviso de tienda fuera de servicio

Se reutilizan accepting_orders y closed_message de administración. No se añaden
horarios automáticos, proveedores, migraciones ni cambios en los pedidos.

- La tienda permanece navegable; el carrito se conserva y se puede preparar.
- El layout público y la página independiente de combos muestran el aviso.
- El checkout deshabilita cotizar y confirmar mientras está cerrado.
- Para ver la reapertura se recarga la página; no se añade polling.
- El mensaje personalizado se escapa en Blade. Vacío o espacios usan el mismo
  texto predeterminado tanto en la vista como en las respuestas del servidor.
- La protección del servidor ya existente sigue rechazando nuevos pedidos aunque
  se envíe un POST manual o la tienda cierre después de cargar el formulario.
- Se preserva la recuperación de pedidos ya registrados y su seguimiento.

## Pruebas

```bash
php artisan test --filter=StoreAvailabilityTest
php artisan test --filter=CartCheckoutConfirmationTest
php artisan test
npm ci
npm run build
php artisan view:clear
```

StoreAvailabilityTest contiene 10 casos contando proveedores de datos:
avisos en carta y combos, botones y reapertura, dos POST HTML, dos POST JSON,
creación directa, mensaje predeterminado y escape HTML.
Las pruebas existentes de confirmación cubren recuperación con la tienda cerrada.

Revisión manual: cerrar desde administración, visitar carta/combos/carrito/checkout,
comprobar aviso y botones; reabrir, recargar y comprobar que permiten continuar.
No se requiere migrate. El build recoge las clases de la nueva vista.

PHP/Node y la prueba visual quedan pendientes de ejecutar en un entorno operativo;
el asistente solo realizó revisión estática.
