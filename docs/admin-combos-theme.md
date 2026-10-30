# Administración de combos: tema y navegación

Las vistas de listado y creación/edición ahora usan layouts.admin, con su menú, logo, avisos y metadatos noindex. Reutilizan catalog-surface y admin-catalog-surface para la paleta crema/terracota y los controles del catálogo administrativo.

Se conservan rutas, formularios, CSRF, campos, validaciones del servidor y lógica JavaScript de componentes/precios. Se agregan asociaciones de etiquetas y nombres accesibles para controles dinámicos. La casilla de publicación describe el carrusel del catálogo. El listado utiliza la paginación compartida y una tabla con desplazamiento horizontal en pantallas estrechas.

## Validación

Revisión estática: rutas y atributos de envío iguales; identificadores únicos y etiquetas asociadas; JavaScript idéntico al retirar únicamente clases y nombres accesibles, con sintaxis comprobada usando datos vacíos en V8. No se ejecutaron PHP, compilación Vite ni navegador en el entorno de trabajo.

En el servidor de pruebas:

```sh
php artisan test
npm ci
npm run build
php artisan view:clear
```

No requiere migraciones.

Comprobar en móvil y escritorio:

- Listado vacío/con datos, paginación, menú activo y aviso de guardado una sola vez.
- Crear y editar un combo con imagen, platos, adicionales y recomendaciones.
- Agregar/quitar componentes, cambiar cantidades y comprobar precio referencial/ahorro; intentar repetir un componente.
- Provocar un error de validación y verificar que se conservan los datos y componentes.
- Cambiar publicación/disponibilidad y verificar el resultado en el carrusel público.
- Recorrer con teclado los campos, componentes y botón de guardado.
