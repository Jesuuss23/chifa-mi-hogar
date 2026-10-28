# Identidad del negocio y logo

En Administración → Configuración general, el formulario «Identidad y datos del negocio» guarda el titular, correo, sitio web, RUC y logo por separado de los pagos y delivery.

Datos iniciales: Rossi Rivadeneyra Jara, chifamihogar@gmail.com y https://chifamihogar.com.
El RUC queda nulo hasta completarlo. El ejemplo del formulario no se guarda. Se valida el formato de 11 dígitos, no la inscripción ni validez tributaria.

## Logo

Se puede subir logo.jpeg, PNG o WebP (máximo 2 MB y 4096 × 4096 píxeles). Se usa ImageService existente: original privado y variantes WebP públicas con nombres UUID. El encabezado y pie usan la miniatura. No requiere proveedor nuevo.

Alternativa manual: public/images/brand/logo.jpeg. El logo subido desde administración tiene prioridad. Si no existe ninguno, se muestran las iniciales CM sin una imagen rota. El archivo manual no se optimiza automáticamente.

Guardar sin archivo conserva el logo. Reemplazarlo genera otra URL y conserva el recurso anterior, por si está referenciado en otro lugar. No hay eliminación automática de recursos antiguos ni botón para quitar el logo en esta entrega.

## Instalación y pruebas

Ejecutar php artisan migrate y, si aún no existe el enlace público de almacenamiento, php artisan storage:link.
Después de desplegar, limpiar vistas compiladas con php artisan view:clear.

Pruebas en el entorno de pruebas habitual:
php artisan test --filter=BrandSettingsTest
php artisan test --filter='Checkout|AdminOrderPaymentAndDispatch|OrderWhatsappRetry'

Las pruebas de imagen usan GD/WebP e Intervention, como el servicio de imágenes existente. Usan discos falsos y RefreshDatabase.

Este cambio prepara los datos y el logo. No publica aún textos legales ni implementa consentimiento, Turnstile, Open Graph o la paleta nueva.
