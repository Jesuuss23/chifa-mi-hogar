# Referidos: primera entrega y banners compactos

## Alcance

Esta entrega prepara campañas y atribución de invitaciones. NO genera giros,
cupones ni contabiliza compras verificadas. El administrador y el cliente ven
esa limitación explícitamente. No publicitar premios todavía.

La rama parte del PR #83 (banners), que aún no estaba fusionado en main al
iniciar este trabajo. Revisar primero esa dependencia. No incluye la API Android
del PR #85 ni la autoasignación delivery del PR #84.

## Administración

Negocio → Referidos permite crear borradores con fechas en hora de Lima,
activar una campaña, pausarla o finalizarla. Las fechas se convierten a la zona
de almacenamiento configurada. El fin es exclusivo: en ese instante deja de
aceptar invitaciones. Solo puede existir una campaña en estado activo, incluso
si está programada o vencida; finalizar/pausar antes de activar otra.

Cada cambio de estado registra actor y fecha. Los datos de una campaña no se
reescriben: crear otra para nuevas condiciones. No se permite borrar ni
reactivar campañas finalizadas. No se activa ninguna campaña al migrar.

## Cliente

Durante una campaña vigente, Mi cuenta muestra un código aleatorio estable.
Se genera al abrir Mi cuenta, también para cuentas antiguas. No revela email,
teléfono o ID. El cliente lo copia y comparte; los enlaces compartibles y el
autocompletado al registrarse quedan para una entrega posterior.

El carrito de una cuenta cliente muestra el campo de invitación. Invitados y
personal no pueden usarlo. Se aplica antes de cualquier primer pedido, incluso
si luego se cancela: esta regla conservadora impide vinculación retroactiva.
La atribución es única por cliente para toda su vida, no se reinicia por campaña.
La invitación conserva la campaña en la que fue vinculada.

## Seguridad y reintentos

- CSRF, autenticación, rol cliente y cinco solicitudes por minuto.
- Validación estricta del código, consultas parametrizadas y Blade con escape.
- Solo se utiliza el ID de la sesión; se ignoran IDs, importes y premios enviados.
- Rechaza código propio, desconocido, dueño no cliente, cambio de referente y
  ciclos. Las cadenas se limitan a 100 niveles para acotar trabajo por solicitud.
- Índices únicos para código, dueño del código y cliente referido.
- Transacción y bloqueo del registro StoreSetting, el mismo que utiliza
  OrderService al crear pedidos: serializa vinculación, primer pedido y cambios
  de campaña. Todos los futuros escritores deben respetar ese orden de bloqueo.
- Reenviar el mismo código devuelve éxito sin duplicar, incluso si la campaña
  se pausó tras el primer envío. No se depende de deshabilitar un botón.
- Un corte de conexión no implica deshacer el servidor: recargar muestra el
  estado persistido; volver a enviar la misma operación es seguro.
- La creación administrativa usa una clave UUID única para evitar duplicación
  al reenviar el formulario. Las transiciones iguales no duplican eventos.

No se afirma que cuentas distintas equivalgan a personas distintas. Antes de
activar premios faltan la validación de primera compra pagada, revisión de abuso,
reversiones, presupuesto y emisión de beneficios. No se bloquean familias solo
por compartir dirección/IP.

## Banners

- Escritorio: recomendado 1600 × 500; mínimo 800 × 200; máximo 3200 × 1600;
  relación ancho/alto entre 2 y 4.
- Móvil: recomendado 900 × 600; mínimo 600 × 300; máximo 1800 × 1440;
  relación entre 1.25 y 2.
- JPG, PNG o WebP; máximo 5 MB. Validación del archivo en servidor antes de
  procesar variantes. No acepta SVG ni destinos externos.
- Los banners existentes se conservan; límites nuevos solo para nuevas subidas.
- Altura máxima de imagen 360 px en escritorio / 240 px en móvil. Imagen
  completa sin recortar, contenedor estable y bordes redondeados. Una imagen
  vertical anterior puede dejar espacio lateral; reemplazarla por el formato nuevo.
- Solo se muestra y envía el selector de destino elegido. Blade ya entrega
  el estado correcto en edición o errores, JavaScript lo actualiza al cambiar.
- Pausar referidos no oculta banners automáticamente: son controles independientes.

## Verificación y despliegue

Antes de servir las vistas, ejecutar las migraciones pendientes y compilar assets:

```bash
composer install
php artisan migrate
php artisan optimize:clear
php artisan test --filter='ReferralFoundationTest|PromotionalBannerTest|CustomerViewSyntaxTest'
node --test tests/Frontend/banner-form.test.cjs tests/Frontend/promotional-banners.test.cjs
npm ci
npm run build
php artisan view:cache
```

Pruebas de reenvío secuencial y restricciones SQL no sustituyen una prueba de
concurrencia real sobre PostgreSQL. Antes de activar campañas con premios se
requiere comprobar simultaneidad, corte de red y verificación repetida de pagos.

Validación local (PHP 8.3 / SQLite): 89 pruebas Laravel, 636 aserciones; seis
pruebas JavaScript; sintaxis PHP, caché de vistas Blade y diff sin errores.
Build Vite aprobado mediante su API con preserveSymlinks, necesario por los
permisos del directorio padre en este entorno Windows; no se cambió la
configuración del proyecto ni sus dependencias. No se realizó inspección visual
en navegador ni prueba concurrente real de PostgreSQL.

## Próximas entregas

1. Vincular la primera compra pagada al referido y gestionar cancelaciones.
2. Presupuesto en céntimos, costos por producto, metas y reservas de premios.
3. Cupones con vencimiento, tope, compra mínima y exclusión de promociones.
4. Ruleta calculada en servidor, resultado persistente por giro y auditoría.

La base no promete premios económicos y no modifica cobros ni estados de pedidos.
