# Editar recomendaciones con imagen anterior

El formulario y el componente catalog-image ya admiten image_path como alternativa
a media_asset_id. El controlador solo comprobaba media_asset_id, por lo que
rechazaba guardar cambios de texto cuando había únicamente una imagen anterior.

La regla required/nullable ahora consulta ambas referencias del registro existente,
igual que el formulario y el controlador de platos. No acepta image_path ni
media_asset_id enviados por el cliente para omitir la carga.

- Sin archivo nuevo se conservan las referencias actuales.
- Crear o editar sin ninguna imagen sigue requiriendo archivo.
- Un archivo nuevo sigue pasando image, mimes y max:5120.
- La sustitución sigue utilizando ImageService y media_asset_id.
- No se mueven, eliminan ni verifican físicamente archivos antiguos: el criterio
  sigue siendo la referencia guardada, como en el flujo existente de platos.

## Validación

```bash
php artisan test --filter=RecommendationImageEditingTest
php artisan test --filter=RecommendationSlugTest
php artisan test
```

Seis casos: imagen anterior, imagen optimizada, creación sin archivo, edición
sin imagen, sustitución inválida y sustitución válida. ImageService está simulado.

Revisión estática realizada. PHPUnit no ejecutado por el bloqueo conocido del
entorno. No requiere migrate ni npm build.
