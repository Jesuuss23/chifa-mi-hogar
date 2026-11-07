# Tema Navidad

Elegir Administración → Apariencia → Navidad → Previsualizar. El tema no se activa
al desplegar esta rama: seleccionar Navidad y pulsar Aplicar tema para publicarlo.
Se conserva la selección guardada. No hay migración ni dependencias nuevas.

## Diseño
- Verde pino, rojo, crema y dorado; Manrope local y botones redondeados.
- Portada con bastones en las esquinas, hojas/bayitas, gorrito, luces doradas estáticas
  y saludo navideño. Se conservan el H1, enlaces, platos, precios y metadatos existentes.
- 24 copos CSS en escritorio y 12 en móvil, contenidos detrás del contenido de
  la portada. No cubren menús flotantes ni interceptan clics.
- Pausar nieve funciona con checkbox nativo y CSS incluso sin JavaScript.
  La pausa se limita a la página actual; no crea cookies ni guarda datos.
- Con prefers-reduced-motion se muestra nieve estática y se oculta el control.
- La vista previa es estática porque es un documento inert/sandbox sin interacción.
  Para ver la animación, aplicar el tema y abrir la carta pública.
- Login, perfil, carrito y otras pantallas reciben la paleta sin adornos ni nieve.
- SVG locales, sin imágenes pesadas, bibliotecas adicionales, servicios ni llamadas externas.

## Validación
No se ejecutaron PHP, Vite ni navegador en el entorno de edición (falló el inicio del
sandbox local). La revisión estática no sustituye las pruebas del servidor.

```sh
php artisan test --filter=ChristmasThemeTest
php artisan test --filter=AdminAppearanceTest
php artisan test --filter=PrivateWebResponsesTest
npm ci
npm run build
php artisan view:clear
```

Comprobar antes de fusionar:
1. Previsualizar Navidad en los dos anchos sin alterar el tema público.
2. Aplicar Navidad. Revisar con carta vacía, con foto destacada, categorías y combos.
3. En 320/375 px, revisar que los bastones no tapen texto ni aparezca scroll horizontal.
4. Pausar/reanudar con ratón y teclado, también con JavaScript desactivado.
5. Activar movimiento reducido en el dispositivo: no debe caer nieve.
6. Probar enlaces, carrito y formularios; revisar imágenes/fuentes y noindex de preview.
7. Volver a Formal u Original y comprobar que desaparecen todos los adornos.

Verano queda para otra entrega.
