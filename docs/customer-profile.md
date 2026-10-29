# Perfil del cliente y autocompletado

## Comportamiento

- El registro conserva sus campos y controles de seguridad. Su redirección existente a Mi cuenta muestra ahora el formulario de perfil antes de los pedidos.
- Permite guardar nombre, celular y, opcionalmente, dirección, referencia y ubicación en el mapa.
- No es obligatorio completar un perfil para comprar; invitados y clientes sin perfil conservan el checkout.
- El correo se muestra para referencia; este formulario no cambia correo, contraseña ni rol.
- Teléfono peruano de 9 dígitos. Una dirección exige el par latitud/longitud válido y viceversa.
- Cambiar el texto de dirección borra la selección del mapa y requiere marcar nuevamente el punto.
- GPS únicamente al pulsar el botón; también se puede elegir el punto manualmente.
- Quitar dirección limpia el formulario; solo se persiste al pulsar Guardar mis datos.
- La ruta PUT /mi-cuenta/perfil exige auth + customer + CSRF y limita peticiones. Solo usa el usuario autenticado, no un user_id enviado por el navegador.
- Perfil separado en customer_profiles con un registro por usuario y borrado en cascada al eliminarlo.

## Checkout

Nombre/correo/celular y destino guardados son valores iniciales editables.
old() tiene prioridad tras errores/cotización. Si existe cualquier dato de entrega anterior, no se completan las piezas faltantes con coordenadas del perfil.
Se identifica la dirección guardada y se ofrece Actualizar dirección para este pedido.
Cambiar dirección o punto exige confirmar el mapa y oculta la cotización anterior; el servidor mantiene su recálculo al confirmar.
No se escribe en el perfil desde quote/confirm. La dirección predeterminada se actualiza solo desde Mi cuenta.
Los pedidos existentes tampoco cambian al editar el perfil.

## Migración y despliegue

```bash
git fetch origin
git switch feat/customer-profile-prefill
php artisan migrate
npm ci
npm run build
php artisan route:clear
php artisan view:clear
php artisan test --filter='CustomerProfileTest|CustomerCheckoutDefaultsTest'
php artisan test
node --test tests/Frontend/customer-profile.test.cjs tests/Frontend/order-tracking.test.cjs
```

Requiere la migración create_customer_profiles_table antes de abrir Mi cuenta/checkout. No se modifica ninguna columna de usuarios ni pedidos.
Si utilizas cache de rutas/vistas, regenerarla después de validar.
Revertir la migración elimina los perfiles guardados, por lo que no debe usarse como paso rutinario de despliegue.

## Validación

15 casos PHPUnit nuevos: permisos, protección de identidad, coherencia de ubicación, actualización/borrado de dirección, autocompletado, old() parcial, invitados, usuarios sin perfil y pedido sin sobrescribir perfil.
4 pruebas frontend nuevas: restauración de coordenadas, invalidación al editar dirección, coordenadas cero, borrado y GPS bajo acción explícita.

La sintaxis JavaScript se comprobó en V8 y las cuatro pruebas del mapa pasaron en una simulación de DOM/Leaflet con adaptadores. Esto no equivale a ejecutar Node, Leaflet real ni un navegador.
PHPUnit, el ejecutor Node, npm build y revisión visual NO ejecutados: bloqueo de permisos del entorno local.

Revisar manualmente:
1. Crear cuenta y completar perfil desde Mi cuenta; recargar y verificar lo guardado.
2. Probar mapa, GPS denegado, arrastre del pin y quitar dirección.
3. Comprar con destino guardado, cambiarlo solo para el pedido y confirmar que el perfil sigue intacto.
4. Provocar error de validación y comprobar que persisten los valores editados.
5. Cotizar, cambiar destino, volver a cotizar y confirmar.
6. Verificar móvil/escritorio, invitados, Yape/efectivo y enlace de Google Reseñas.

Se amplió la descripción de datos tratados en Privacidad para reflejar el perfil opcional. No se añaden proveedores ni APIs: se reutiliza Leaflet/OpenStreetMap.
