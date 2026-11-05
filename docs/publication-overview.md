# Resumen de publicación e imagen de marca

Administración → Configuración muestra ahora el estado real guardado de las páginas informativas:

- Incompletas: lista los campos faltantes y enlaza sus entradas.
- Borrador completo: invita a revisar las vistas previas antes de publicar.
- Publicadas: confirma la disponibilidad de las tres páginas.

La lista y la disponibilidad pública comparten missingLegalFields() en StoreSetting. Conserva exactamente los siete campos requeridos por la política existente; no introduce requisitos ni interpreta cumplimiento normativo. El RUC sigue con el tratamiento existente y debe revisarse con los datos reales del negocio.

Abrir configuración no publica ni modifica datos. Se mantiene el formulario y su acción explícita de publicación. El resumen refleja los valores guardados, no old() ni modificaciones sin enviar.

Identidad muestra la imagen para compartir, de 1200×630, con carga diferida. Tanto la imagen como el enlace usan el identificador de versión derivado del logo por BrandShareImageService, igual que los metadatos públicos. Después de guardar otro logo se consulta una URL distinta. Las plataformas sociales tienen su propia caché y deben revisarse aparte.

## Validación

PublicationOverviewTest añade 14 casos: campos requeridos, estados guardados sin mutación, versión de imagen/enlace, permisos. Revisión estática realizada. PHPUnit y compilación no ejecutados por bloqueo del entorno Windows al iniciar procesos.

```bash
php artisan test --filter='PublicationOverviewTest|LegalPagesTest|BrandSettingsTest|BrandShareImageTest'
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Sin migraciones ni dependencias. Comprobar configuración en móvil/escritorio, seguir los enlaces a campos incompletos, guardar un logo y comprobar la vista previa. Completar datos reales, revisar las tres vistas previas y publicar solo desde el formulario cuando corresponda. Esta entrega no publica ni rellena datos ficticios.
