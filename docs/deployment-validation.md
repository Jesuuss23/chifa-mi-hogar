# Validación operativa antes de publicar

## Diagnóstico local sin envíos

```bash
php artisan app:check-deployment
php artisan app:check-deployment --json
```

Lee la configuración que Laravel tiene cargada (incluida su caché). No muestra valores de claves, tokens, correos ni URLs de retorno. No escribe en la base de datos, no ejecuta jobs, no envía correo ni llama a proveedores. El arranque normal de Laravel sigue siendo necesario.

- CONFIGURADO: pasa la comprobación local descrita; no equivale a una prueba externa.
- REVISAR: falta configuración básica o requiere comprobación manual.
- DESACTIVADO: proveedor social opcional apagado; el acceso directo se conserva.
- Código de salida 1 si hay algún REVISAR; 0 si no hay observaciones locales. El 0 no certifica publicación ni elimina los pendientes manuales.

Comprueba entorno de producción, debug apagado, URL HTTPS sin credenciales incrustadas, cookie segura, controlador persistente de sesión, Turnstile y dominios no vacíos, parámetros de proveedores sociales habilitados, tipo de transporte de correo, tipo de cola y presencia de parámetros WhatsApp.

Límites: no verifica validez de claves, contenido de archivos .env, tablas, permisos de disco, conexión de base de datos, certificados, DNS, dominio autorizado, versiones externas, workers ni tareas programadas. Correo compuesto/personalizado requiere revisión; SMTP presente no demuestra credenciales ni entrega. Cola persistente configurada no demuestra que haya un worker. Turnstile debe probarse realmente: claves de prueba o dominios incorrectos pueden superar la comprobación de presencia.

Si modificas configuración, aplícala mediante el procedimiento habitual de despliegue y vuelve a ejecutar el informe. No es necesario compartir .env ni secretos para revisar el resultado.

## Pendientes que necesitan evidencia real

| Bloque | Comprobación | Evidencia esperada |
| --- | --- | --- |
| Marca y SEO | Logo definitivo, imagen al compartir, sitemap y rutas públicas | Vista previa correcta y URLs públicas del dominio definitivo |
| Datos informativos | Datos reales del negocio, RUC pendiente y publicación explícita | Revisión de las tres páginas desde el panel antes de publicar |
| Privacidad y cookies | Comparar servicios realmente cargados con el inventario | Inspección del navegador; consentimiento solo para servicios opcionales reales |
| Accesos | Registro directo, Google/Facebook habilitados, Turnstile y recuperación | Acceso y retorno correctos; correo recibido en una cuenta de prueba |
| Compra | Invitado/cliente, dirección editable, entrega, efectivo/Yape/Plin | Pedido controlado con importes y estados esperados |
| WhatsApp | Webhook, permisos, interacción reciente, worker y scheduler | Aviso recibido para el pedido de prueba y registro coherente |
| Delivery | Autorización, ubicación actual, pérdida y recuperación de conexión | Prueba desde móvil sin exponer seguimiento privado |
| Opinión | Valoración y enlace Google después de entrega | Botón disponible según el estado y valoración en el pedido correcto |
| Respaldo | Copia de BD y archivos; restauración aislada | Recuperación probada sin sobrescribir producción |
| Imágenes históricas | Revisar y, si corresponde, ejecutar conversión por lotes | Registro de lotes y revisión visual; ver legacy-catalog-images.md |

No se marcan estas comprobaciones como completadas por fusionar una rama o pasar PHPUnit. Realizar compras/envíos externos de prueba de forma deliberada desde el entorno elegido; el diagnóstico no los inicia.

## Pruebas de esta entrega

```bash
php artisan test --filter=DeploymentConfigurationReportTest
```

Se añaden seis pruebas de códigos de salida, ocultación de secretos, ausencia de envíos, parámetros inseguros, proveedores opcionales y controladores no reconocidos. No se pudieron ejecutar: el entorno de edición no permite iniciar la terminal por fallo de aprovisionamiento del sandbox. No requiere migraciones, npm build ni nuevas dependencias.

La suite completa usa RefreshDatabase en varias clases: ejecutarla únicamente con la base de pruebas aislada definida en phpunit.xml, no con una base de producción ni configuración cacheada que apunte a ella.
