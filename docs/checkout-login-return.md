# Volver a la compra después de identificarse

Desde checkout un invitado puede iniciar sesión o crear una cuenta antes de completar datos de entrega. La entrada usa continue=checkout, que el servidor reconoce como una opción cerrada, no una URL. Se mantiene la compra como invitado.

El contexto se conserva en sesión durante 30 minutos y se consume al completar login/registro. Funciona con contraseña, Google y Facebook. La vinculación de proveedores desde Mi cuenta conserva su destino original. El acceso normal sin contexto conserva su comportamiento anterior.

Carrito y checkout_token permanecen en la sesión al regenerar su identificador. Si el carrito se vacía durante el acceso, el retorno lleva al carrito. Contextos vencidos, futuros o malformados no activan el retorno. No se confía en URLs o nombres de rutas enviados en formularios/callbacks.

Los errores de login, cancelación OAuth y cambio entre entrar/registrarse mantienen el contexto para reintentar. Volver a una página de acceso sin parámetro conserva un contexto vigente; un parámetro continue explícito no permitido lo descarta. Un nuevo ingreso desde checkout reinicia el plazo.

No guarda un borrador de campos sin enviar. La pantalla lo advierte y ofrece identificación antes de completar entrega/pago. Al volver, el controlador existente aplica los datos del cliente y el perfil guardado. No cambia cotización, disponibilidad, confirmación/idempotencia, importes ni APIs.

## Validación

```bash
php artisan test --filter='CustomerCheckoutReturnTest|CustomerGoogleLoginTest|CustomerFacebookLoginTest|CustomerProfileTest'
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Sin migraciones ni dependencias nuevas. CustomerCheckoutReturnTest cubre contraseña/registro, ambos proveedores, cancelaciones, vinculación, errores, contexto inválido/vencido, carrito vacío y destino fijo.

En el entorno actual hay Node, pero no PHP/Composer, así que no se ejecutó PHPUnit ni compilación Blade. Revisión estática realizada; los proveedores se simulan en las pruebas y requieren comprobación real. No se realizó una compra en producción.

Probar con un carrito real de platos/combos: iniciar sesión desde checkout, verificar contenido y datos guardados, editar dirección para este pedido y cotizar. Repetir con Google/Facebook y registro nuevo. Confirmar que el invitado puede seguir sin cuenta. Probar cancelación social y volver sin identificarse. Verificar que iniciar sesión normalmente sigue llevando a Mi cuenta.

Siguiente revisión: continuidad del borrador de entrega y presentación de errores/cotización; esta entrega no promete conservar texto sin enviar.
