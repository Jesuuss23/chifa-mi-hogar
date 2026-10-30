# Tema de administración de WhatsApp

La vista de WhatsApp reutiliza la paleta y controles administrativos mediante catalog-surface y admin-catalog-surface. Incluye título de página, etiquetas asociadas a los campos de nombre/teléfono y encabezados accesibles en el historial. Los identificadores de edición contienen el ID del administrador para distinguir cada formulario.

El aviso de éxito se muestra una sola vez desde layouts.admin. El resumen de errores tiene role=alert. Se conservan las señales verdes de actividad y las advertencias ámbar; los estados desactivados usan el color destructivo del tema.

No cambia el controlador, la integración Meta, los permisos, los comandos ni el envío de notificaciones. Tampoco cambia los valores iniciales ni el comportamiento de recuperación de formularios. Cada administrador conserva su formulario y botón de guardado independiente.

## Verificación

Comparación estática: rutas, atributos de envío, valores, límites y directivas Blade idénticos, salvo la retirada intencional del aviso duplicado. Etiquetas asociadas a campos existentes e IDs distintos entre alta y edición.

No se ejecutaron Laravel, Vite ni navegador desde el entorno de trabajo. No se agregan pruebas que solo reproduzcan clases CSS.

```sh
php artisan test
npm ci
npm run build
php artisan view:clear
```

No requiere migraciones.

Comprobar en un entorno de pruebas:

- Pantalla sin administradores y con varios administradores; historial vacío y con comandos.
- Navegación con teclado y clic en las etiquetas de nombre/teléfono de cada formulario.
- Alta y edición con datos válidos y con errores: revisar mensajes y un solo aviso de éxito.
- Permisos de control/recepción y protección del último administrador activo.
- Vista móvil: historial desplazable, formularios y botones dentro del ancho disponible.
