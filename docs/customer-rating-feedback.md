# Corrección de errores de valoración por pedido

En Mi cuenta pueden aparecer varios pedidos entregados con formulario de valoración. Antes, todos leían el mismo comentario y los mismos errores de sesión, y la puntuación elegida se perdía al fallar el envío.

El controlador identifica el formulario con el ID del pedido de la ruta (sobrescribe cualquier rating_order_id enviado) y asigna una bolsa de errores distinta por pedido. La vista restaura comentario y estrellas únicamente en ese pedido. Los valores no escalares se descartan al presentar el formulario para evitar fallos de renderizado. Los errores siguen visibles si el pedido ya fue valorado y su formulario dejó de aparecer.

Se mantienen propiedad del pedido, cliente autenticado, entrega completada, rango 1–5, comentario opcional de hasta 1000 caracteres, bloqueo transaccional y una sola valoración por pedido. No habilita valoración de invitados ni cambia Google Reseñas.

## Validación

Añadidos cuatro casos a CustomerOrderRatingTest: dos pedidos con contexto manipulado, entradas de tipo array, error por valoración duplicada y datos antiguos de otro formulario. Las pruebas anteriores de permisos, estados y duplicados permanecen.

Revisión estática realizada. PHPUnit y compilación no ejecutados: el entorno Windows bloqueó el arranque de procesos.

```bash
php artisan test --filter=CustomerOrderRatingTest
php artisan test
php artisan view:cache
php artisan view:clear
```

Sin migraciones ni dependencias. Prueba manual con dos pedidos entregados: enviar uno sin puntuación o con un comentario inválido; comprobar que solo ese pedido muestra errores y conserva sus datos. Enviar después una valoración válida y comprobar que no permite duplicarla.
