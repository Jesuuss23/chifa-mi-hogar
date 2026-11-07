# Apariencia de la tienda

En Administración → Apariencia se puede elegir Formal, Original, Navidad, Verano o Salvia. La migración
conserva Formal como valor inicial, también para la fila de configuración existente.
Solo se guardan identificadores permitidos; no se acepta CSS o HTML proporcionado por el usuario.

## Uso
1. Previsualizar abre un modal con la carta real en 1100 px (escritorio) o 375 px (móvil).
   En pantallas pequeñas se puede desplazar horizontalmente la vista.
2. La vista es exclusivamente visual: no ejecuta scripts ni permite enlaces o formularios.
   Esto significa que no reproduce las interacciones del carrusel ni del menú.
   Sin JavaScript, el enlace abre la misma vista protegida en otra pestaña.
3. Cerrar la vista, marcar el tema y pulsar Aplicar tema.
4. Para volver al anterior, seleccionarlo y aplicarlo.

La vista previa requiere sesión de administrador, usa noindex/private/no-store y no
guarda nada en la configuración ni en la sesión. Un parámetro en la URL pública
no puede activar temas alternativos. La selección se renderiza desde el servidor,
sin depender de JavaScript ni cambiar URLs o contenido SEO público.

El tema se aplica a la carta, detalle, combos, carrito, acceso y cuenta de clientes.
Original recupera sus colores/tipografía/acabados, conservando la distribución actual.
El administrador utiliza Salvia independientemente del tema público; delivery mantiene su diseño. Un interruptor claro/oscuro queda fuera de esta entrega.

Navidad añade adornos y nieve en la portada; en la vista previa la nieve es estática.
Ver [detalles de Navidad](christmas-theme.md).
Verano añade sol y ondas estáticas: [detalles de Verano](summer-theme.md).

Salvia incorpora la paleta del nuevo panel: [detalles de diseño](sage-admin-design.md).

## Validación antes de fusionar
No se ejecutaron PHP, Vite ni pruebas de navegador en el entorno de edición.

```sh
php artisan migrate
php artisan test --filter=AdminAppearanceTest
php artisan test --filter=PrivateWebResponsesTest
npm ci
npm run build
php artisan view:clear
```

Comprobar manualmente el modal con los cinco temas, teclado (Escape/cierre y retorno
del foco), scroll móvil/escritorio y carga de fotos/fuentes. Mientras se previsualiza,
abrir la tienda en otra sesión y confirmar que sigue igual. Aplicar Original,
revisar carta/login/cuenta/carrito; restaurar Formal y revisar botones redondeados.
El servidor debe permitir iframes del mismo origen (no X-Frame-Options DENY).
