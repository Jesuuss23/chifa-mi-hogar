# Compatibilidad de imágenes del catálogo

El componente compartido catalog-image resuelve archivos del disco public con CatalogImageService. Afecta a las vistas que usan ese componente: categorías, platos, adicionales, categorías de adicionales, recomendaciones y combos.

Orden de búsqueda:
1. Variante solicitada: thumbnail, card o detail (card por defecto).
2. Otras variantes existentes: card, detail, thumbnail, sin repetir consultas para una misma ruta.
3. image_path histórico, si existe.
4. Indicador «Sin imagen» si no hay archivo.

Se admiten rutas relativas, con separadores Windows y con prefijo storage/ o /storage/. Se usa la URL configurada del disco public. Se rechazan direcciones externas, rutas absolutas ajenas a /storage/, segmentos de navegación y rutas codificadas/consultas. El original privado de MediaAsset nunca se publica como alternativa.

Esta entrega no modifica registros, convierte archivos ni elimina imágenes. La migración histórica a WebP queda para una entrega posterior. Los métodos URL de MediaAsset y las vistas que no usan catalog-image conservan su comportamiento actual.

La comprobación de existencia realiza hasta cuatro consultas al disco por imagen, normalmente una. No se comprueba que el contenido pueda decodificarse ni que el enlace público de Nginx funcione. Un archivo corrupto o una configuración incorrecta de storage:link/servidor web requieren revisión operativa. Las URLs externas históricas no se descargan: deben sustituirse mediante la carga habitual de imágenes.

## Pruebas

CatalogImageCompatibilityTest cubre variantes, archivos ausentes, fallback histórico, prefijos, los seis modelos, placeholder, originales privados, rutas inválidas y escape HTML. Usa discos falsos y relaciones en memoria, sin imágenes reales ni llamadas externas.

No se pudieron ejecutar PHPUnit ni compilación: el entorno de edición no permitió iniciar comandos (error de aprovisionamiento del sandbox). Ejecutar:

```bash
php artisan test --filter=CatalogImageCompatibilityTest
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

No requiere migraciones ni dependencias nuevas.

Comprobar visualmente un plato con imagen antigua, otro con media_asset, un combo y una recomendación. Para simular un archivo ausente usar un entorno de pruebas; no borrar imágenes de producción. Confirmar que APP_URL y el enlace público de almacenamiento corresponden al dominio del sitio.
