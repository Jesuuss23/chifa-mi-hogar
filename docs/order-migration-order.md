# Orden de las migraciones de pedidos

Las dos migraciones históricas comparten el prefijo 2026_10_24_085832.
Laravel ordena primero create_order_items_table y después create_orders_table.
La FK inline original referenciaba una tabla aún inexistente en PostgreSQL.

## Cambio

- Se conserva cada nombre de archivo y la columna order_id.
- create_order_items_table crea la columna sin FK.
- create_orders_table añade order_items_order_id_foreign después de crear orders,
  con el mismo destino y ON DELETE CASCADE.
- Su down elimina primero esa FK, también compatible con la definición original,
  antes de eliminar orders. Después se revierte order_items.
- No se cambian datos, modelos, flujos ni dependencias.

En instalaciones que ya registraron ambas migraciones, Laravel no repite sus up.
No hay una nueva migración que ejecutar ni es necesario reconstruir la base.
Este arreglo corrige instalaciones limpias y la reversión del par histórico.
No pretende reparar bases modificadas manualmente o migraciones parcialmente ejecutadas.
Las migraciones dependientes posteriores deben revertirse antes de este par.

## Validación

```bash
php artisan test --filter=OrderMigrationOrderTest
php artisan test
```

Los cinco casos usan una conexión SQLite en memoria independiente, incluso si el
resto de la suite utiliza RefreshDatabase. Cubren creación en orden real,
metadatos de FK, cascada, rechazo de huérfanos, reversión con filas, nueva aplicación,
compatibilidad con FK inline e historial registrado que no se reproduce.

Para repetir estos mismos casos sobre PostgreSQL, usar una base exclusiva de pruebas
y un usuario con permiso CREATE SCHEMA:

```bash
ORDER_MIGRATION_TEST_PGSQL_URL='postgresql://usuario:clave@127.0.0.1:5432/chifa_migration_test' php artisan test --filter=OrderMigrationOrderTest
```

Cada caso crea y elimina únicamente un esquema aleatorio order_migration_test_*.
La URL es opcional, no se obtiene del .env de producción y no debe apuntar a producción.
Sin ella, solo se prueba SQLite: un resultado verde no equivale a probar PostgreSQL.
No ejecutar migrate:fresh, migrate:reset ni rollback sobre producción para validar esto.

No se requieren npm build, cambios de configuración de la aplicación ni migraciones nuevas.
PHP/Composer/PostgreSQL no pudieron ejecutarse en el entorno del asistente por un
fallo de permisos al iniciar procesos; queda pendiente ejecutar los comandos anteriores.
