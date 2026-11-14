# Descuentos por invitación y cupones

Esta entrega sustituye el plan de ruletas/metas por dos porcentajes configurables.
Los códigos y las invitaciones existentes se conservan. Ninguna campaña obtiene
descuentos automáticamente al migrar: ambos porcentajes y el tope parten en cero.

## Administrador

En **Negocio → Referidos**:
1. Crear un borrador o pausar una campaña existente.
2. Abrir **Configurar descuentos**: porcentaje del invitado, porcentaje del dueño
   del código, compra mínima elegible (S/), descuento máximo por pedido (S/) y
   vigencia del cupón (1–365 días).
3. Elegir **Aplicar también a combos**, desmarcado por defecto. Esta opción, el
   mínimo y el tope se aplican a ambos beneficios.
4. Guardar y activar la campaña dentro de sus fechas. Solo una puede estar activa.

Un porcentaje positivo exige un tope mayor que cero. Si se ofrece un cupón al
referente, también debe habilitarse el descuento del invitado. Se permiten
porcentajes enteros entre 0 y 100: el administrador decide el costo de la promoción.
El sistema limita el descuento; no conoce el costo de producción ni garantiza
rentabilidad. Las condiciones se editan únicamente en borrador o pausa.

El pago verificado por un administrador genera un solo cupón para el referente.
Si el repartidor cobra efectivo, el pedido se entrega como antes; el administrador
debe revisar **Cobros de reparto por revisar → Validar referido** para emitir
el cupón. La aprobación no vuelve a cobrar ni cambia el estado del pedido.
Cupones emitidos muestra beneficiario, porcentaje, tope, vencimiento, estado y
enlace al pedido origen.

Pausar/finalizar impide nuevas invitaciones y nuevos descuentos de invitación.
No revoca descuentos de pedidos ya confirmados ni cupones emitidos: estos conservan
sus condiciones hasta vencer. El banner se activa/desactiva por separado.

## Cliente y cálculo

- Solo cuentas con rol cliente. El código se vincula en el carrito antes del primer
  pedido, sin autorreferencias, ciclos ni cambio posterior de referente.
- Cada cuenta conserva una sola atribución de por vida, no una por campaña.
- El descuento de invitación se aplica automáticamente a un pedido elegible durante
  la campaña de esa invitación. Un pedido que no cumple el mínimo o solo contiene
  combos excluidos no consume el beneficio.
- El importe elegible suma platos/recomendaciones y sus adicionales. Con combos
  desactivados se excluye toda la línea del combo; con la opción activada se incluye.
  El delivery nunca se descuenta ni cuenta para el mínimo.
- Cálculo en céntimos: mínimo entre el importe elegible, el tope y
  `floor(importe_elegible × porcentaje / 100)`. No se redondea hacia arriba.
- Al verificar el pago del pedido elegible se consume el beneficio y, con aprobación
  administrativa, se emite el cupón del referente. Un pedido con total cero no
  genera premio. No se otorgan premios retroactivos por pedidos sin descuento reservado.
- **Mi cuenta** muestra los cupones personales disponibles, incluso si la campaña
  ya terminó. En el checkout se puede elegir uno o **Guardar mis cupones**.
- Solo un beneficio por pedido: un cupón elegido sustituye al descuento de invitación.
  Quote, confirmación HTML y checkout JSON usan el mismo cálculo en servidor.
- Un cupón vencido, ajeno, reservado o usado se rechaza; no se acepta el importe,
  porcentaje, usuario o total enviado por el navegador.

## Reservas, reintentos y cancelación

El pedido y su descuento se guardan en una misma transacción. Confirmar reserva el
beneficio: otro pedido no puede utilizarlo mientras el primero esté pendiente.
La misma clave de checkout devuelve el pedido ya creado, sin consumir otra vez.

Cancelar un pedido sin pago verificado libera la reserva; no desvincula el código.
Un cupón liberado conserva su vencimiento original. Un pago rechazado no genera
premio, pero conserva la reserva hasta cancelar o resolver el pedido.
Los pedidos pagados no se pueden cancelar con el flujo actual; esta entrega no
agrega reembolsos ni reversiones de premios.

La verificación del pago y la emisión del cupón participan de la transacción
existente. El observador debe ejecutarse sin cola y dentro de esa transacción.
No sustituir las mutaciones de pedidos por actualizaciones masivas que omitan
eventos Eloquent. Las nuevas integraciones deben mantener este contrato.

Checkout usa bloqueo de StoreSetting y luego de la invitación/cupón. Pagos y
cancelaciones bloquean el pedido y luego su beneficio; no toman StoreSetting.
Índices únicos garantizan un cupón por invitación, un cupón por pedido origen
y una ficha de descuento por pedido. Una reserva se actualiza de forma condicional.
Los pedidos guardan una copia de las condiciones: editar la campaña no los recalcula.

## Despliegue y comprobación

Esta rama parte de main. La corrección del retorno de estados del PR #90 es
independiente y debe conservarse/fusionarse también; esta entrega no modifica
Admin/OrderController ni su JavaScript.

```bash
php artisan migrate
php artisan optimize:clear
php artisan test --filter='ReferralFoundationTest|ReferralDiscountTest|CartCheckoutConfirmationTest|JsonCheckoutTokenTest|AdminOrderPaymentAndDispatchTest'
npm run build
php artisan view:cache
```

Las migraciones conservan datos existentes; requieren ejecutarse antes de servir
las nuevas vistas. No hacer rollback después de activar beneficios: eliminaría
su historial. El despliegue inicial no activa porcentajes ni inclusión de combos.

Revisar en un entorno de pruebas con dos cuentas: vincular código, carrito mixto,
cotizar y confirmar, verificar pago, revisar cupón en la cuenta del referente,
usarlo opcionalmente y cancelar un pedido pendiente para comprobar su liberación.
Probar también pausa, vencimiento y cobro de efectivo por repartidor.

Las pruebas automatizadas cubren rutas reales, permisos, manipulación de campos,
restricciones únicas SQL, reenvíos, rollback transaccional y combinaciones de productos.
Se validaron también 35 pruebas de referidos sobre PostgreSQL 18.6 y cuatro
escenarios con cuatro procesos PHP simultáneos: reenvío del mismo checkout,
verificación de pago, canje del mismo cupón con claves distintas y cancelación.

La prueba concurrente reproducible está en `tests/Integration/referral-concurrency.php`.
Requiere APP_ENV=testing, DB_CONNECTION=pgsql y una base NUEVA y VACÍA cuyo nombre
termine en `_referral_concurrency_test`, configurada mediante las variables DB_*.
Ejecutar `php tests/Integration/referral-concurrency.php`. Crea las tablas y
datos ficticios, no borra tablas existentes y no envía notificaciones.
No ejecutar en la base del negocio ni incluirla en el despliegue.

Se corrigió también StoreSetting::current(): su clave interna debe ser siempre 1,
incluso cuando la secuencia del motor haya avanzado. Esa fila se usa para
serializar checkout y la vinculación de códigos. No se habilita asignación
masiva de su id desde formularios.

No se cortó físicamente la red del navegador; se probaron reenvíos y fallos
transaccionales. Tampoco se afirma que cuentas distintas representen personas
distintas: la aprobación administrativa sigue siendo parte del control de abuso.

