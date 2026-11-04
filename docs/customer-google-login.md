# Acceso de clientes con Google

## Comportamiento

Registro directo sigue operativo, con Turnstile y límites existentes. Google es una alternativa de identidad: solo permite un correo verificado por Google, impone role=customer en altas y nunca concede acceso al panel administrativo o del repartidor.

- Continuar con Google aparece en registro y login únicamente cuando configuración y claves están completas.
- Alta nueva: guarda nombre, correo e identidad estable sub; contraseña local aleatoria protegida por el cast hashed, no entregada al cliente.
- Retorno: busca provider+provider_id, nunca por correo. No sobrescribe nombre, correo, contraseña ni perfil.
- Correo ya registrado: no vincula automáticamente. El usuario debe entrar con su contraseña y usar Vincular Google en Mi cuenta.
- Vinculación: exige cliente autenticado, POST con CSRF, confirmación de contraseña y cuenta Google con el mismo correo. No mueve una identidad ya vinculada ni reemplaza otra identidad Google.
- Después del acceso: Mi cuenta, donde puede completar el perfil. Conserva carrito y datos de sesión. No acepta una URL de retorno externa.
- Cancelar o fallar muestra un mensaje y permite reintentar o utilizar el acceso directo.
- Google no aporta teléfono ni dirección en este flujo; no se solicitan permisos de Gmail, contactos, Drive o ubicación.
- No se persisten tokens de Google, fotografías ni contraseña de Google. Solo identidad vinculada.
- No se solicitan refresh tokens ni se envían notificaciones.

## Seguridad

Authorization Code desde el servidor, state aleatorio ligado a sesión con vigencia de diez minutos, PKCE S256, state consumido una vez y registro atómico de uso en caché para rechazar callbacks concurrentes/repetidos. Confirmación de que el usuario de la sesión sigue siendo el mismo al volver.

El backend obtiene el token desde Google y consulta su endpoint HTTPS UserInfo autenticado. No interpreta JWT sin verificar ni acepta perfil enviado por el navegador. Se valida sub y correo verificado. HTTP sin redirecciones, timeouts y sin reintentos del código de un solo uso. Errores remotos no se muestran ni registran con tokens. La ruta callback redirige sin renderizar recursos, con no-store y no-referrer.

La tabla tiene unicidad por identidad y por usuario/proveedor. Las altas y vínculos son transaccionales. Conflictos simultáneos de unicidad fallan de forma controlada; el usuario puede reintentar. No constituye una prueba real de concurrencia en PostgreSQL.

Configurar caché y sesiones persistentes; si hay varios servidores, compartir ambos almacenes. Mantener SESSION_SAME_SITE=lax para el retorno GET desde Google y cookies seguras bajo HTTPS. No guardar códigos/tokens ni query strings OAuth en logs de proxy/servidor. Los límites de inicio (5/minuto por IP) y callback (10/minuto) no sustituyen la protección de infraestructura frente a abuso distribuido.

## Configuración y despliegue

No agrega paquetes Composer ni cambia APIs existentes. Usa el cliente HTTP de Laravel y los endpoints de identidad Google. No habilitar APIs de pago para este acceso.

1. Aplicar la rama y ejecutar php artisan migrate (crea customer_social_accounts).
2. Crear credenciales OAuth de tipo aplicación web en Google Auth Platform. Configurar marca, dominio, contacto y enlaces informativos reales.
3. Registrar exactamente https://chifamihogar.com/auth/google/callback como URI de redirección. Staging debe tener sus propias credenciales y URI HTTPS.
4. Si el proyecto está en pruebas, registrar usuarios de prueba; completar la publicación/revisión que indique Google antes de abrirlo a todos.
5. Guardar en .env del servidor, sin subir secretos a Git:

GOOGLE_LOGIN_ENABLED=true
GOOGLE_LOGIN_CLIENT_ID=valor_del_cliente
GOOGLE_LOGIN_CLIENT_SECRET=secreto_del_cliente
GOOGLE_LOGIN_REDIRECT_URI=https://chifamihogar.com/auth/google/callback

6. Ejecutar php artisan config:cache y php artisan view:clear.
7. Probar alta, acceso repetido, cancelación y vinculación con cuentas de prueba. Comprobar cookies, retorno de sesión y carrito en móvil real.

Desactivar GOOGLE_LOGIN_ENABLED y regenerar caché oculta el botón y bloquea las rutas. Las cuentas creadas solo con Google no tienen contraseña conocida: necesitarán reactivar el proveedor o una recuperación segura de contraseña (pendiente del plan); no borrar sus identidades ni revertir la migración rutinariamente.

Se añade información condicional del acceso con Google en Privacidad y aviso junto al botón. No carga scripts Google al visitar registro/login: la conexión comienza al pulsar el botón.

## Pruebas

php artisan test --filter=CustomerGoogleLoginTest
php artisan test
npm ci
npm run build

Los casos HTTP simulan Google y prohíben solicitudes externas. Cubren configuración, scopes/PKCE/state, vigencia, cancelación, replay, nuevas cuentas y retorno, conservación del carrito, colisiones de email/roles, vinculación con contraseña, conflicto de identidad, cambio de sesión, perfiles inválidos y errores HTTP/red.

Revisión estática realizada. PHP, Composer, Node/Vite y navegador no ejecutados: el entorno no puede iniciar procesos por bloqueo de sandbox. La validación real de Google también requiere las credenciales del propietario.

## Fuentes técnicas

- https://developers.google.com/identity/protocols/oauth2/web-server
- https://developers.google.com/identity/openid-connect/openid-connect
