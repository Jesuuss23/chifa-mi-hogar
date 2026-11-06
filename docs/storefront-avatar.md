La cabecera de la tienda muestra la foto del cliente autenticado en los enlaces de cuenta de escritorio y menú móvil. Reutiliza /mi-cuenta/foto, que sirve únicamente la foto de la sesión y conserva no-store; no expone rutas de almacenamiento ni permite elegir otro usuario. Si la ruta no pertenece al cliente o el archivo no existe se mantiene la inicial. Administradores y repartidores conservan su inicial.

Pruebas: php artisan test --filter=StorefrontAvatarTest y php artisan test --filter=CustomerAvatarTest. Después npm ci, npm run build, php artisan view:cache y php artisan view:clear. Verificar subir, reemplazar y quitar desde Mi cuenta, volver a inicio y revisar también el menú móvil.

No requiere migraciones. Pruebas/build no ejecutados en el entorno de edición por bloqueo de inicio de la terminal.
