# Borrador al identificarse desde checkout

Los botones de login y registro envían el formulario por POST y guardan sus campos permitidos en la sesión durante 30 minutos. No es autoguardado: cerrar la página sin pulsarlos no conserva lo escrito.

Se conserva contacto, dirección, referencia, coordenadas, notas y selección de pago. Los campos vacíos de contacto usan el perfil al volver. Si dirección y referencia están vacías, se utiliza la entrega del perfil. En caso contrario, los cuatro campos de entrega se recuperan juntos: una ubicación incompleta o inválida queda vacía y debe marcarse otra vez. El perfil nunca se modifica.

El borrador se asocia al token de compra y se vincula al cliente al recuperarlo. Se descarta si caducó, tiene fecha futura, pertenece a otro token/usuario, o el carrito está vacío. Solo admite destinos login/registro, con CSRF, middleware guest y throttle. No guarda precios, totales ni campos arbitrarios.

Guardar elimina la cotización anterior. Al volver se debe cotizar nuevamente. Cotizar elimina el borrador y deja prevalecer el formulario enviado y sus errores. Confirmar, incluido un pedido ya registrado, también lo elimina. La expiración se comprueba al leer; el almacenamiento físico sigue el controlador de sesiones existente. No añade cookies ni servicios externos.

Los botones funcionan sin JavaScript. Con JavaScript la salida a identificarse permite datos incompletos; confirmar conserva las validaciones completas de entrega/pago y el recálculo del servidor.

## Validación

Sin migraciones ni dependencias nuevas.

```bash
node --test tests/Frontend/checkout-auth-draft.test.cjs
php artisan test --filter='CheckoutAuthDraftTest|CustomerCheckoutReturnTest|CustomerCheckoutDefaultsTest|CustomerProfileTest'
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Ejecutado: cinco pruebas Node del manejador real, todas aprobadas (borrador incompleto, ubicación obligatoria, efectivo con cambio, envío válido y doble envío). PHPUnit y compilación Blade no ejecutados: PHP/Composer no están disponibles en el entorno. La compilación Vite queda pendiente en el repositorio completo.

Se incluyen 14 casos PHPUnit para login y recuperación, ruta de registro/regreso invitado, perfil, GPS parcial, whitelist, datos actuales, caducidad, token/propietario, carrito vacío y cotización.

Prueba manual: escribir dirección y notas; iniciar sesión con contraseña, Google o Facebook, o registrarse; revisar datos y punto; cotizar. Repetir sin ubicación, con campos vacíos, cancelando el acceso y sin JavaScript. Comprobar que no cambió el perfil y no se creó un pedido al guardar.
