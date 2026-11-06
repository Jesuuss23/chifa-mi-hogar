# Manrope local para el tema formal

Se incluyen dos archivos WOFF2 variables (latino y latino ampliado) por un total de 39 956 bytes. Cubren el español y los rangos latinos declarados; otros alfabetos siguen usando las alternativas del sistema. Pesos normales 200–800; peticiones de 900 se resuelven al máximo disponible. No se incorporan Instrument Serif ni las fuentes mono en esta entrega.

La familia se registra como Manrope, que ya utiliza el tema formal. No se usa local() para depender de una versión instalada en el dispositivo. font-display: swap mantiene el texto visible con una alternativa mientras llega la fuente; puede haber un pequeño reajuste de texto, por lo que falta comprobar estabilidad visual real.

Vite procesa las URLs relativas desde resources/css/manrope.css, emite los WOFF2 con nombres versionados en public/build/assets y ajusta el CSS. El despliegue debe incluir todos esos archivos, no solo el CSS. No hay dependencias npm nuevas, llamadas a Google Fonts, importaciones CSS remotas ni SDKs. No se precargan fuentes globalmente: el navegador las solicita cuando la familia y los caracteres se utilizan.

El tema original y el panel conservan sus familias actuales. Los botones de proveedores sociales mantienen la tipografía de sus estilos propios. Los binarios y su licencia se guardan en resources/fonts/manrope; SOURCE.md registra tamaños y hashes de origen.

## Validación

Comprobados los tamaños, cabecera WOFF2, correspondencia con SHA de origen, rutas relativas, rangos y licencia. No se pudo ejecutar build ni revisar navegador: el entorno continúa bloqueando el inicio de la terminal.

```bash
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

En el navegador, revisar inicio, carrito, cuenta y acceso en móvil/escritorio. En Herramientas de desarrollo → Red, filtrar por font: la fuente debe provenir de los assets del sitio (o del host propio configurado para assets), sin solicitudes a fonts.googleapis.com/fonts.gstatic.com. Inspeccionar fuentes renderizadas: Manrope. Probar ñ, áéíóú, ¿?, signos de moneda, números y nombres largos. Confirmar que los botones y textos no desbordan después de cargar.

No requiere migraciones ni cambios de configuración. Siguiente entrega: selector/modal de temas con vista previa y activación.
