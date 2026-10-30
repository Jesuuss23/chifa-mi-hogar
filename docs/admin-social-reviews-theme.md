# Tema de redes sociales y reseñas

Las vistas de redes sociales, reseñas de Google y valoraciones de pedidos reutilizan la paleta administrativa y sus controles. Valoraciones pasa a layouts.admin, incorporando menú, logo y metadatos noindex del panel.

Redes sociales conserva su formulario y casillas; cada URL tiene una etiqueta asociada por clave de plataforma. El aviso de guardado aparece una sola vez desde el layout. Los errores de redes/Google usan role=alert.

El historial de valoraciones incorpora encabezados de columna, caption accesible, ancho mínimo con desplazamiento horizontal y paginación compartida. Conserva los cálculos de promedio/distribución y los comentarios escapados. Google mantiene enlaces, atribución, estados de error/vacío y servicio existente. No se modifica backend, APIs ni puntuaciones.

## Verificación

Comparación estática de rutas, atributos de formulario y condiciones/directivas Blade. Se conserva el contenido dinámico, salvo el cambio intencional de paginación y la retirada del aviso de éxito duplicado. No se ejecutaron PHP, Vite ni navegador en el entorno de trabajo.

```sh
php artisan test
npm ci
npm run build
php artisan view:clear
```

No requiere migraciones.

Revisar en móvil/escritorio:

- Guardado de URLs y casillas Mostrar; errores de validación y un único aviso de éxito.
- Reseñas de Google con datos, sin comentarios y con error de configuración/servicio.
- Valoraciones vacías y con datos, promedio, distribución, comentarios largos y paginación.
- Menú activo de valoraciones y navegación por teclado en los formularios y enlaces.
