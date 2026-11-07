# Orden de platos antes de guardar

Al abrir Crear/Editar y al cambiar Orden, se consulta la disponibilidad después de
300 ms sin cambios. Si está ocupado, se indica el nombre del plato y se impide el
envío normal mediante validación nativa. Un botón ofrece el siguiente orden libre
sugerido por el servicio existente; al usarlo se comprueba otra vez.

El orden sigue siendo general entre categorías, incluyendo platos no disponibles.
Los eliminados no reservan número. Se permite mantener el orden actual al editar,
incluidos empates antiguos, igual que en el servicio existente.

La consulta es privada auth+admin, con límite de peticiones y no-store/noindex.
No modifica datos. Los nombres se presentan con textContent, nunca como HTML.
Se cancelan peticiones anteriores y se ignoran respuestas tardías. Tras volver
desde la caché de navegación se consulta otra vez.

El aviso no reserva el número: otro administrador puede ocuparlo antes de guardar.
La comprobación transaccional del servidor se conserva y ahora incluye el nombre
del plato en el error. Si la consulta falla, está pendiente o no hay JavaScript,
el servidor sigue validando al enviar. No se bloquea el formulario indefinidamente.

## Validación
Revisión estática y análisis sintáctico JS. PHP/Vite/navegador no ejecutados en
el entorno de edición; pendientes de validación en el servidor.

```sh
php artisan test --filter=ProductOrderTest
npm ci
npm run build
php artisan view:clear
```

Revisar número ocupado, libre, 0, fuera de rango, sugerencia, edición sin cambiar
orden y cambios rápidos en el campo. Probar sin conexión/sin JS y dos sesiones
administrativas ocupando el mismo número antes del envío.
Sin migraciones ni dependencias nuevas.
