# Foto de perfil opcional

Mi cuenta permite subir, reemplazar y quitar una foto propia. No se exige durante registro o compra, ni se descarga desde proveedores sociales.

## Almacenamiento

Se reutiliza Intervention Image 4/GD ya instalado, con un servicio separado del catálogo: el ImageService existente genera variantes públicas y conserva originales, lo que no corresponde a una foto privada.

Se aceptan JPG/PNG/WebP reales, hasta 2 MB y de 32 a 2048 píxeles por lado. Se decodifican y recodifican a WebP de hasta 256x256 conservando proporción. La presentación usa un recorte circular mediante CSS. No se conserva el original ni su nombre. No admite SVG/GIF ni archivos de texto disfrazados de imagen.

Solo se guarda una ruta interna aleatoria en users.avatar_path, oculta en serialización y fuera de fillable. Archivo en disco local privado: customer-avatars/{id}/{uuid}.webp. No usa public, storage:link ni MediaAsset. La ruta GET /mi-cuenta/foto sirve únicamente la foto del cliente autenticado, sin aceptar IDs/rutas del navegador y con no-store/nosniff.

PUT/DELETE exigen auth+customer, CSRF y límite de cinco solicitudes/minuto. La actualización bloquea la fila del usuario para obtener la referencia anterior correcta. Primero escribe el archivo nuevo, luego confirma la referencia y finalmente elimina el anterior. Si falla la persistencia, intenta borrar el nuevo y mantiene la referencia previa.

La limpieza se limita al directorio del usuario y al formato de nombre generado. Si el disco impide borrar, se registra una advertencia sin datos personales: puede quedar un archivo privado huérfano. Una interrupción abrupta entre escritura y commit también puede dejarlo. Revisar permisos y logs; no se incluye eliminación automática masiva. El mensaje de quitar confirma que se retiró de la cuenta, no garantiza borrado de copias de seguridad.

## Despliegue

```bash
php artisan migrate
php artisan test --filter='CustomerAvatarTest|CustomerProfileTest|CustomerPasswordTest|CustomerSocialAccountTest|CustomerGoogleLoginTest|CustomerFacebookLoginTest'
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Aplicar migración antes de servir el código nuevo; no revertir la columna mientras el formulario siga activo. La reversión de esquema no borra archivos.

Requiere GD con soporte WebP (ya usado por el catálogo) y permisos de escritura en storage/app/private. Configurar upload_max_filesize al menos 2M y post_max_size mayor, con límites de Nginx compatibles; no se modifica el servidor en esta entrega.

No instala paquetes nuevos. La foto se procesa dentro de la petición y no necesita cola. Agregar el almacenamiento privado a las copias de seguridad existentes.

## Verificación

No se pudieron ejecutar PHP/Node por el fallo de preparación del sandbox. Se realizó revisión estática y se añadió CustomerAvatarTest para validación, conversión, almacenamiento privado, reemplazo/eliminación, propiedad, roles, fallos de escritura y límites de peticiones. Falta ejecución real.

Probar con dos clientes: subir desde uno, comprobar vista propia y que el otro no puede obtener esa foto. Reemplazar, quitar y recargar; verificar que el perfil, mapa, pedido y seguridad siguen funcionando. Probar teléfono, imagen vertical/horizontal, imagen demasiado grande y archivo inválido.

La política de privacidad incluye el uso voluntario de la foto. No se publica en catálogo, reseñas ni seguimiento.

Referencia de la interfaz instalada:
https://github.com/Intervention/image/blob/4.4.0/src/Interfaces/ImageInterface.php

Siguiente: recorrido de compra del cliente (catálogo, carrito, entrega y pago).
