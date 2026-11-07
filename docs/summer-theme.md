# Tema Verano

Disponible en Administración → Apariencia → Verano. Previsualizar no cambia el
tema público. Marcar Verano y pulsar Aplicar tema para activarlo; cualquier otro
tema se puede restaurar desde el mismo formulario.

## Alcance
- Turquesa, coral, arena y blanco, Manrope local y botones redondeados.
- Sol y ondas vectoriales en la portada. Son estáticos, sin controles de movimiento,
  JavaScript, peticiones externas, cookies ni dependencias nuevas.
- Adornos detrás del contenido, con pointer-events: none y aria-hidden.
- Foto, H1, precios, metadatos, enlaces y flujos existentes se conservan.
- El resto de páginas de clientes usa la paleta sin los adornos de portada.
- Admin y delivery conservan su diseño.
- La rama no cambia la selección guardada ni necesita una migración.
- La vista previa conserva el aislamiento, noindex y no-store del selector.

## Validación
El entorno local no pudo iniciar procesos por un error de sandbox. No se ejecutaron
PHP, Vite ni pruebas visuales de navegador; se hizo revisión estática de la integración.

```sh
php artisan test --filter=SummerThemeTest
php artisan test --filter=ChristmasThemeTest
php artisan test --filter=AdminAppearanceTest
php artisan test --filter=PrivateWebResponsesTest
npm ci
npm run build
php artisan view:clear
```

Revisión manual antes de fusionar:
1. Mantener Navidad o Formal activo, previsualizar Verano y comprobar en otra sesión
   que la tienda pública sigue mostrando el tema anterior.
2. Aplicar Verano y revisar portada con/sin foto, combos y categorías.
3. Revisar anchos 320, 375 y 1100 px; texto sin sol superpuesto, ondas al pie,
   foco visible con teclado, sin desbordamiento horizontal.
4. Probar el acceso, perfil y carrito: paleta correcta, sin adornos sobre formularios.
5. Restaurar Navidad, Formal y Original; comprobar que los temas no se mezclan.
