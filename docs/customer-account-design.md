# Organización visual de Mi cuenta

Mi cuenta se divide en datos/entrega, pedidos y acceso/seguridad, con enlaces internos disponibles sin JavaScript. En escritorio el índice ocupa una columna lateral; en móvil queda antes del contenido. Las secciones permanecen visibles: el mapa no se inicializa dentro de pestañas ocultas.

Se reutilizan logo, colores y tipografía de la marca. El resumen muestra el correo del cliente autenticado. Los pedidos muestran el total paginado del controlador y las mismas etiquetas en español que seguimiento. Se conserva el estado original como alternativa para valores nuevos.

Cambios de presentación:
- Perfil con campos y selectores del mapa conservados; errores asociados a campos. El resumen de errores del perfil solo muestra sus propios campos.
- Pedidos en tarjetas, estados legibles, valoración agrupada con fieldset/legend, etiqueta de comentario por pedido y mensaje de valoración guardada.
- Paginación mantiene el ancla de pedidos. Contraseña y proveedores siguen visibles en seguridad, con sus formularios originales.
- Navegación por teclado, foco del tema existente, enlaces de salto y controles grandes. Sin dependencias ni JavaScript nuevo.
- Meta noindex/nofollow y no-referrer en Mi cuenta.

No cambia rutas, consultas, permisos, validación ni datos almacenados. No añade seguimiento público nuevo ni modifica el flujo de valoración. La foto de perfil sigue pendiente para una entrega separada.

## Validación

```bash
php artisan test --filter='CustomerProfileTest|CustomerOrderRatingTest|CustomerSocialAccountTest|CustomerPasswordTest|CustomerGoogleLoginTest|CustomerFacebookLoginTest'
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Revisión estática: campos/IDs del perfil, rutas/CSRF de formularios y parciales de seguridad conservados. No se ejecutaron PHP, Node ni navegador por el fallo de preparación del sandbox. No se añadieron pruebas que solo reproduzcan clases CSS. Se requiere revisión real del mapa, de accesibilidad y de la presentación.

Comprobar en 320/360/390px, tablet y escritorio; zoom 200% y teclado. Probar cuenta nueva, datos guardados, GPS permitido/denegado, cambio de dirección, errores de perfil y seguridad, sin pedidos, muchos pedidos/paginación, pedido entregado sin valoración y valoración existente. Confirmar privacidad entre dos clientes con la suite existente. El comentario y errores de valoración siguen el comportamiento previo de Laravel old input; esta entrega no modifica el backend de valoraciones.

Siguiente paso: foto de perfil opcional, con validación y tratamiento de imágenes, en rama independiente.

## Corrección de recuperación incluida

Se detectó y corrigió una expresión Blade malformada en el campo email de customer/auth/password.blade.php, introducida en el cambio visual anterior: aria-invalid había quedado dentro de auth()->user(). Los atributos vuelven a estar fuera de value. La suite existente CustomerPasswordTest renderiza la pantalla de solicitud y restablecimiento, por lo que debe ejecutarse también. Verificar manualmente el correo precargado con y sin sesión.
