# Tema del detalle de entrega

La pantalla de detalle del repartidor reutiliza storefront-theme y catalog-surface: fondo crema, tarjetas y enlaces terracota, bordes y foco del tema compartido. Los datos largos pueden partirse y las filas de importes permiten envolver contenido en pantallas estrechas.

Se conservan las advertencias ámbar y los botones verde/rojo de seguimiento. El destino sigue siendo la ubicación del cliente registrada en el pedido.

## Verificación

Comparación estática: bloque PHP de coordenadas/URLs/estado y script GPS idénticos al original. IDs, enlaces, atributos de botones, anuncios de estado y condiciones Blade conservados. No cambia backend, permisos ni APIs. No se ejecutaron Laravel, Vite, navegador ni GPS real desde el entorno de trabajo.

```sh
php artisan test
npm ci
npm run build
php artisan view:clear
```

No requiere migraciones.

En un pedido de prueba asignado al repartidor, comprobar:

- Detalle en móvil/escritorio con dirección, notas y nombres largos.
- Pagos en efectivo y digitales, importes y vuelto.
- Destino válido y destino sin coordenadas.
- Abrir punto exacto e iniciar navegación hacia el cliente.
- Pedido en camino: iniciar/detener seguimiento con permiso GPS en HTTPS.
- Pedido en preparación: aviso de que el GPS estará disponible en camino.
- Navegación por teclado y regreso a Mis entregas.
