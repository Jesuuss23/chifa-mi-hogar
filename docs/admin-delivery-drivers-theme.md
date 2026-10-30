# Gestión de repartidores: acceso y tema

Se agrega Repartidores al menú administrativo, junto a Pedidos, utilizando la ruta existente admin.delivery-drivers.index. Conserva el comportamiento del menú en móvil/escritorio y marca la sección activa con aria-current.

La vista reutiliza el tema y los controles administrativos. Incluye título de página, etiquetas asociadas a nombre, correo, contraseña y confirmación, y resumen de errores con role=alert. El aviso de éxito queda únicamente en layouts.admin.

No cambia el alta de usuarios, la asignación del rol delivery, validación, hash de contraseñas, permisos, límite de peticiones, listado ni rutas. El portal del repartidor queda fuera de este cambio.

## Verificación

Revisión estática: rutas, campos, valores, atributos de validación/autocompletado y condiciones Blade conservados; etiquetas asociadas a sus campos. El único cambio del layout es el enlace nuevo a una ruta ya existente.

No se ejecutaron PHP, Vite ni navegador en el entorno de trabajo. No se agregan pruebas que reproduzcan cambios de clases CSS.

```sh
php artisan test
npm ci
npm run build
php artisan view:clear
```

No requiere migraciones.

Revisión manual en un entorno de pruebas:

- Abrir Repartidores desde el menú y verificar el estado activo.
- Revisar listado vacío/con datos, incluidos nombres o correos largos.
- Crear una cuenta de prueba y comprobar un único aviso de éxito.
- Comprobar errores de correo duplicado, contraseña menor de 12 caracteres y confirmación distinta.
- Revisar el formulario con teclado y en pantallas móvil/escritorio.
