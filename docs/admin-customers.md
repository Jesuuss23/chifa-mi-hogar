# Clientes registrados

Administración → Clientes ofrece búsqueda por nombre, correo o teléfono y páginas
de 25 cuentas, ordenadas desde las más recientes. Solo incluye role=customer:
ni repartidores/administradores ni compras de invitados.

Ver detalles abre un modal con datos cargados al solicitar la ficha, no incrustados
para todos los clientes de la lista. Sin JavaScript el mismo enlace abre una página.
El modal tiene cierre con Escape/botón, devuelve el foco y elimina el contenido al
cerrarse. Hay un enlace alternativo si falla la carga o caduca la sesión.

La ficha muestra nombre, correo, verificación registrada, fecha de registro, teléfono,
foto opcional, proveedores Google/Facebook vinculados, dirección, referencia,
coordenadas y fecha de actualización de entrega. No deduce el método original de
registro a partir de los accesos vinculados. Las fechas indican la zona de app.timezone.
La dirección del perfil no necesariamente coincide con una dirección usada en un pedido.

## Acceso y datos
- Rutas GET dentro del grupo auth+admin. Consulta sin editar ni borrar usuarios.
- Búsqueda agrupada bajo role=customer, parámetros enlazados y comodines literales.
- Nombre, dirección y demás texto se imprimen con escape de Blade.
- No se seleccionan contraseñas/tokens en el listado o ficha ni IDs privados de proveedores.
- Foto servida desde almacenamiento local privado, validando pertenencia de ruta;
  no se expone una URL de storage ni se modifica el acceso del cliente a su foto.
- Páginas, fragmento y foto llevan noindex y private/no-store por el middleware existente.
- Google Maps es un enlace externo explícito, sin mapa incrustado ni llamada automática.
- No hay migraciones, nuevas APIs ni dependencias.

## Validación pendiente de ejecución
El entorno local no pudo iniciar procesos por fallo del sandbox. No se ejecutaron
PHP, Vite ni pruebas de navegador. Se revisó la integración de forma estática.

```sh
php artisan test --filter=AdminCustomerDirectoryTest
php artisan test --filter=CustomerAvatarTest
php artisan test --filter=PrivateWebResponsesTest
npm ci
npm run build
php artisan view:clear
```

Revisar listado vacío/con varias páginas, búsqueda, perfiles sin datos y con foto,
cierre con Escape/foco, móvil y carga lenta/error/sesión caducada. Desactivar JS y
confirmar que la ficha abre como página. Entrar como cliente/repartidor y confirmar
que no pueden abrir lista, detalle ni foto administrativa.
