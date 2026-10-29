# Opinión después de comprar

La confirmación del pedido muestra «Danos tu opinión» con el enlace ya guardado en Configuración → google_reviews_url.
Funciona para invitados y cuentas registradas. No requiere Google Places ni otras llamadas HTTP.
Si el enlace está vacío o no es una URL HTTP(S) válida, no aparece el bloque.
No sustituye ni condiciona las valoraciones internas: todos los pedidos con confirmación pueden acceder, sin filtrar por opinión o estado de pago.

El enlace se conserva tal como fue configurado (salvo espacios exteriores); no se agregan nombre, teléfono, código ni token del pedido.
Abre una pestaña nueva con noopener/noreferrer y referrerpolicy=no-referrer.
No confirma que el cliente publicó una reseña, no pide una puntuación determinada y no abre Google automáticamente.

## Validación

No requiere migraciones ni nuevas variables de entorno.

```bash
git fetch origin
git switch feat/post-purchase-google-review
npm run build
php artisan view:clear
php artisan test --filter='PostPurchaseGoogleReviewTest|CartCheckoutConfirmationTest|JsonCheckoutTokenTest'
php artisan test
```

Comprobar con un pedido de prueba de invitado y otro registrado:
- botón visible tras confirmar;
- enlace abre la ficha correcta del negocio;
- al vaciar el campo en administración, el bloque desaparece;
- WhatsApp y seguimiento conservan su funcionamiento.

11 casos PHPUnit preparados. Ejecución pendiente: el entorno del asistente no permite ejecutar procesos locales.

## Próxima mejora: perfil reutilizable

Requisito del usuario pendiente de implementación, en una rama independiente:
- completar/editar nombre, teléfono, dirección, referencia y ubicación del mapa desde la cuenta;
- autocompletar checkout con esos datos, respetando los valores editados y old() tras errores;
- indicar «Esta es la dirección guardada en tu cuenta» y ofrecer «Actualizar dirección para este pedido»;
- editar dirección y coordenadas de forma coherente y recalcular delivery al cambiarlas;
- modificar la entrega de un pedido no debe sobrescribir el perfil;
- guardar una nueva dirección predeterminada debe ser una acción explícita;
- conservar compra como invitado y comprobar que cada usuario solo accede a su propio perfil.

Esta rama no agrega todavía campos ni cambia el formulario de registro.
