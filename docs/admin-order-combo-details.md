# Componentes de combos en el detalle administrativo

Administración → Pedidos → Detalle identifica los combos y muestra sus componentes guardados: nombre, tipo, cantidades incluidas/seleccionadas/adicionales por combo y total a preparar en esa línea.

Ejemplo: una línea de 2 combos con 3 chaufas seleccionados por combo muestra 6 chaufas a preparar. El cargo adicional se muestra por combo y se aclara que ya está incluido en el importe de la línea. Las líneas con personalizaciones distintas permanecen separadas como fueron guardadas.

Se utilizan los datos históricos de OrderItemComboComponent, no el catálogo actual. No se recalculan importes ni se modifica el pedido. Un combo antiguo sin detalle guardado muestra un aviso; no se inventan componentes. El controlador carga los componentes junto a los extras para evitar consultas por cada línea.

## Validación

Se incluyen seis casos en AdminOrderComboDetailTest: cantidades y ausencia de mutaciones, escape de texto, combo histórico sin componentes, producto normal y acceso denegado a cliente/repartidor.

Revisión estática realizada; PHPUnit/Blade/Vite no ejecutados porque el entorno Windows bloqueó el arranque de procesos.

```bash
php artisan test --filter=AdminOrderComboDetailTest
php artisan test
npm ci
npm run build
php artisan view:cache
php artisan view:clear
```

Sin migraciones ni dependencias. Probar un pedido con dos combos iguales, otro con configuraciones distintas y un plato normal. Confirmar cantidades por línea y que subtotal/total no cambian.

La rama parte del main remoto disponible y es independiente del PR de resumen de publicación.
