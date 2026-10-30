# Tema del acceso y listado del repartidor

Las pantallas de acceso y Mis entregas optan por storefront-theme y catalog-surface para reutilizar la paleta crema/terracota, tipografía local y foco visible. Los estilos quedan limitados a estas vistas.

El acceso conserva etiquetas, autocompletado, correo anterior, CSRF y ruta de autenticación; el error se anuncia con role=alert. Mis entregas permite envolver el encabezado y los estados en pantallas estrechas, mantiene tarjetas completas como enlaces y usa pagination.storefront con etiqueta de entregas. El botón Salir tiene altura mínima de 44 px. Se permite partir textos largos.

Se mantienen noindex/nofollow, estados y enlaces de pedidos. No cambia el controlador, permisos, filtros por repartidor, autenticación ni cierre de sesión. El detalle de entrega y su GPS quedan fuera de este cambio.

## Validación

Comparación estática: rutas, campos, atributos de autocompletado y condiciones Blade conservados. Revisión de compatibilidad con los tokens de storefront-theme.css y la paginación compartida. No se ejecutaron Laravel, Vite ni navegador en el entorno de trabajo.

```sh
php artisan test
npm ci
npm run build
php artisan view:clear
```

No requiere migraciones.

Comprobar en móvil y escritorio:

- Acceso correcto e incorrecto con una cuenta de repartidor; navegación con teclado.
- Listado vacío y con entregas en distintos estados, incluidos nombres largos.
- Paginación con más de 15 pedidos asignados.
- Abrir el detalle y regresar al listado.
- Cerrar sesión y comprobar que la lista vuelve a requerir autenticación.
