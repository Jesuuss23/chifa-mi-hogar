# Protección de registro e inicio de sesión

## Alcance

Los tres portales conservan roles, sesiones y redirecciones.
El registro conserva el rol customer impuesto por el servidor.
No hay cambios en checkout, pedidos, WhatsApp ni proveedores existentes.

Se mantiene throttle:5,1 en registro, login de clientes y repartidores y se añade al login de administración. Limita peticiones por IP y devuelve HTTP 429 con Retry-After. El middleware estándar puede compartir el contador entre rutas del mismo dominio.

Además se cuentan fallos de contraseña por correo normalizado + IP. El contador se comparte entre los tres portales:
- Cinco fallos dentro de una ventana de 900 segundos impiden nuevos intentos para esa combinación.
- La ventana comienza en el primer fallo; no es un bloqueo de 15 minutos desde el quinto.
- Al vencer se puede intentar de nuevo.
- Un login correcto antes del límite borra los fallos.
- Otra IP no queda bloqueada por los fallos de la primera.
- Los errores por rol incorrecto también cuentan.

La caché debe ser persistente en producción (por ejemplo CACHE_STORE=database, ya disponible en Laravel). No usar array en producción. Si existen varios servidores, necesitan un almacén compartido para aplicar los mismos contadores. No configurar todos los proxies como confiables: la IP debe provenir del servidor o de proxies conocidos. Estos límites reducen abuso; no garantizan detener ataques distribuidos ni un límite global de registros.

## Activar Turnstile

La integración se entrega desactivada para no bloquear el registro sin claves. Desplegar esta rama por sí solo NO activa Turnstile.

1. Crear un widget de Turnstile en Cloudflare, modo Managed, autorizado para chifamihogar.com y www.chifamihogar.com si se usa.
2. Guardar las claves directamente en .env del servidor, sin publicarlas en Git:
   
   TURNSTILE_ENABLED=true
   TURNSTILE_SITE_KEY=clave_publica_del_widget
   TURNSTILE_SECRET_KEY=clave_secreta_del_widget
   TURNSTILE_HOSTNAMES=chifamihogar.com,www.chifamihogar.com

3. Ejecutar php artisan config:cache y php artisan view:clear.
4. Comprobar en el navegador el widget y un registro real controlado en el dominio permitido.

No se requiere cambiar DNS ni sustituir hosting. Configurar el dominio de staging por separado para sus pruebas. Las pruebas PHPUnit simulan Siteverify y no requieren claves reales.

La página de registro carga el script solo cuando está activado y existe site key. El servidor verifica cada token antes de consultar la unicidad del correo o crear la cuenta. Se comprueban success estrictamente booleano, hostname de la lista configurada y action=register. No se confía en el Host enviado por el visitante para definir el dominio permitido.

Un token ausente, demasiado largo, inválido, expirado o reutilizado se rechaza. Cloudflare valida expiración y uso único. Las comprobaciones usan timeouts de conexión de 3 segundos y total de 8, sin reintentar automáticamente un token de un solo uso. Una caída de red, respuesta inválida o configuración incompleta con enabled=true impide crear cuentas; los logins y pedidos siguen disponibles.

Para volver al modo anterior de registro, establecer TURNSTILE_ENABLED=false y regenerar la caché de configuración. Esto desactiva deliberadamente la protección antibot; los límites de peticiones siguen activos.

El token no se conserva en old input y no se registran claves ni tokens en logs propios. Se mantienen las exclusiones de contraseñas de Laravel. No se modifica CSRF ni se habilita HTML sin escapar en el formulario.

## Privacidad y experiencia

Turnstile es un servicio externo de seguridad que se carga en el formulario de registro cuando se activa. Su uso debe describirse al preparar la política de privacidad y el inventario de servicios. Esta entrega no implementa aún el banner de consentimiento ni las páginas legales.

Si el script está bloqueado o el token caduca, el servidor muestra un error y permite recargar para generar una verificación nueva. Los errores de otros campos también requieren un nuevo token, ya que Siteverify consume el anterior.

## Validación

php artisan test --filter='AuthenticationRateLimitTest|RegistrationTurnstileTest'
php artisan test --filter='Checkout|AdminOrderPaymentAndDispatch|OrderWhatsappRetry|OrderTrackingPrivacy|BrandSettings'

33 casos nuevos con SQLite, caché array y HTTP simulado. No son pruebas de carga, carreras concurrentes, browser real ni ataques distribuidos.
No se ejecutaron aquí debido al bloqueo de procesos del entorno local. Confirmar en el entorno de pruebas antes de fusionar.

Referencias:
- https://developers.cloudflare.com/turnstile/get-started/server-side-validation/
- https://developers.cloudflare.com/turnstile/get-started/client-side-rendering/
