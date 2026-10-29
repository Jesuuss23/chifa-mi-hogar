# URLs únicas y estables del catálogo

## Problema corregido

Categorías y platos usaban Str::slug(name) directamente al crear y editar.
Nombres distintos como Café y Cafe generaban el mismo slug y chocaban con el índice único.
Además, cambiar el nombre modificaba la URL pública y rompía enlaces anteriores.
Los registros eliminados lógicamente también siguen ocupando su slug en la base de datos.

## Comportamiento

- Al crear desde administración, se conserva el slug limpio si está libre.
- En caso de colisión: cafe-2, cafe-3, etc., contando también los registros retirados.
- Tras 100 candidatos se utiliza un sufijo ULID para evitar una búsqueda secuencial sin límite.
- Un nombre sin caracteres aptos recibe categoria o plato como base.
- Los slugs respetan el tamaño real de la columna, incluido el sufijo.
- El índice único existente sigue siendo la garantía final. Se reintenta hasta tres veces si otro guardado reserva el slug entre consulta e inserción.
- La inserción se realiza en transacción/savepoint y se revierte antes de consultar/reintentar, también al crear productos dentro de su transacción de extras.
- Renombrar una categoría o plato conserva su slug. El formulario explica este comportamiento.
- No se actualizan URLs existentes ni se migran registros.
- Combos ya mantienen su slug al editarse y tienen un sufijo en altas: no se modifica ese flujo.
- El nombre de categoría se limita a 100 caracteres, como su columna actual (formulario/validación permitían 120).

El servicio se usa en altas de administración. Imports/seeders que escriban directamente con Model::create siguen siendo responsables de aportar un slug válido.

## Validación

11 pruebas Feature preparadas: nombres equivalentes/repetidos, soft deletes, renombrados con URL conservada, fallback, longitud, límite de nombre, sufijos ocupados y una colisión determinista entre consulta e inserción.
La prueba de colisión inserta una fila con un listener entre ambas operaciones; no es una prueba multiproceso.
PHPUnit y PHP no ejecutados por bloqueo del sandbox local. Revisado estáticamente.

```bash
git fetch origin
git switch fix/catalog-stable-urls
php artisan view:clear
php artisan test --filter='CatalogSlugTest|StorefrontSeoTest|SitemapTest|CheckoutCategoryAvailabilityTest'
php artisan test
```

Sin migraciones, paquetes ni build de assets.
Comprobar en administración: crear dos platos de igual nombre, renombrar uno y abrir su URL anterior.
Los índices existentes se conservan; no se modifica checkout, precios, imágenes ni APIs.

Referencias de implementación:
- https://api.laravel.com/docs/12.x/Illuminate/Database/UniqueConstraintViolationException.html
- https://laravel.com/docs/12.x/database#database-transactions
