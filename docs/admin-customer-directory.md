# Clientes registrados

Administración → Clientes registrados permite consultar cuentas con rol customer. Incluye listado de 20 por página, búsqueda por nombre/correo/teléfono y ficha individual con nombre, correo, fecha de registro, teléfono, dirección, referencia y coordenadas guardadas.

La dirección del perfil no sustituye a la de un pedido: la ficha lo advierte. Las cuentas sin perfil muestran «Sin registrar». Los pedidos como invitado siguen consultándose en Pedidos. No se agregan funciones de edición, borrado, exportación ni acceso a fotografías privadas.

Las rutas admin.customers.index/show están dentro de auth + admin y reciben la protección existente de noindex/private/no-store. La ficha filtra rol customer incluso al introducir otro ID. Las consultas seleccionan campos explícitos; no cargan contraseñas, tokens ni rutas de avatar. La búsqueda agrupa sus condiciones dentro del filtro de clientes, utiliza parámetros y trata %/_ como caracteres literales.

Pruebas:
```bash
php artisan test --filter=AdminCustomerDirectoryTest
php artisan test --filter=PrivateWebResponsesTest
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Seis pruebas añadidas: roles, paginación, campos seleccionados, búsquedas, escape HTML, perfil incompleto, consulta sin mutación y exclusión de IDs de personal. No ejecutadas por el bloqueo del entorno al iniciar la terminal. Comprobar listado/ficha en móvil y escritorio. Sin migraciones ni dependencias nuevas.

La rama es independiente de la corrección de avatar del encabezado; ambas parten del mismo main y pueden fusionarse por separado.
