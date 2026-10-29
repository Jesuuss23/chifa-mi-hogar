# Checkout solo delivery

La interfaz pública y el negocio ofrecen solo delivery, pero los endpoints JSON
y OrderService admitían pickup. Esto permitía nuevos pedidos sin dirección,
coordenadas ni cálculo de delivery mediante una petición manual.

Se restringe delivery_type a delivery en CheckoutQuoteController,
CheckoutController (incluida su ruta administrativa) y en quote/create de
OrderService. La validación del checkout HTML ya tenía esta restricción.

No se cambian rutas, tarifas, APIs, columnas ni pedidos existentes.
No hay migraciones ni cambios de frontend.

## Compatibilidad

- Los pedidos nuevos requieren delivery; una petición pickup recibe 422.
- El cálculo de delivery y los precios siguen viniendo del servidor.
- El servicio conserva la recuperación idempotente antes de validar una nueva
  modalidad, incluso para un pedido histórico pickup.
- La validación HTTP rechaza cualquier payload pickup, también en reintentos.
  No se elimina ni convierte un pedido histórico: su consulta y seguimiento
  permanecen disponibles. Los reintentos normales de delivery no cambian.
- Los tests existentes que usan pickup para comprobar que administración rechaza
  un despacho inválido se conservan.

## Comprobación

```bash
php artisan test --filter=DeliveryOnlyCheckoutTest
php artisan test --filter=JsonCheckoutTokenTest
php artisan test --filter=CartCheckoutConfirmationTest
php artisan test
```

Los 12 casos nuevos cubren clientes invitados/registrados/administrador, ambas
llamadas al servicio, campos de ubicación obligatorios, importes de delivery
calculados en servidor, reintentos sin duplicados y conservación histórica.

Validación del asistente: revisión estática. No ejecutados PHP ni PHPUnit por el
bloqueo del entorno al iniciar procesos. No se necesita npm build ni migrate.
