# Prioridades: experiencia del cliente

Orden acordado tras priorizar al cliente frente a los retoques administrativos.

1. Acceso con Google y registro directo: fusionado en main. El propietario confirmó credenciales configuradas y login funcionando.
2. Acceso con Facebook: fusionado en main por el propietario; activación real en Meta por verificar. Alta/retorno, vinculación con contraseña, validación del token y manejo de correo ausente. Ver docs/customer-facebook-login.md para configuración y límites de verificación.
3. Recuperación/cambio de contraseña: fusionado en main; entrega real de correo por verificar; ver docs/customer-passwords.md. Incluye establecer contraseña para cuentas sociales e invalidar sesiones anteriores. Gestión de accesos: fusionada en main; permite desvincular Google/Facebook confirmando la contraseña, conserva acceso directo e invalida sesiones anteriores. Ver docs/customer-social-management.md. El propietario confirmó su fusión.
4. Experiencia visual: login, registro y recuperación fusionados en main. Esta entrega organiza Mi cuenta en datos/entrega, pedidos y seguridad; pendiente build y revisión visual del propietario. Opciones claras, completar perfil después del alta, direcciones editables, errores accesibles y diseño móvil. Al terminar este punto: foto de perfil opcional subida por el cliente, según lo solicitado; no importarla automáticamente de proveedores.
5. Recorrido de compra del cliente: catálogo/carrusel, personalización por unidad, carrito, dirección guardada o nueva, cotización y pago; revisar continuidad de sesión al identificarse.
6. Confirmación y seguimiento: estados, ubicación, incidencias de red, reseñas y uso móvil real.
7. Cierre público: logo definitivo, Open Graph, sitemap/indexación, datos legales reales, publicación de páginas y comprobación de cookies en producción. Gestor de consentimiento solo si existen servicios opcionales reales.
8. Correcciones funcionales pendientes del administrador: detalle de componentes de combos, orden único de categorías/platos (definir alcance y reordenación), migración histórica de imágenes y compatibilidad de imágenes antiguas.
9. Retoques administrativos restantes: login y ayuda de comandos WhatsApp.
10. Validación operativa final: pruebas completas, Google/Facebook reales, Turnstile, colas/scheduler WhatsApp, GPS, copias/restauración y revisión móvil/escritorio.

Cada punto se entrega por rama para pruebas y fusión del propietario. Las cuentas de Google/Facebook no sustituyen el registro directo ni la compra como invitado. No se solicitan acceso a buzones, contactos o fotos. La incorporación de cada proveedor incluye revisar lo que realmente comparte y actualizar la información de privacidad.
