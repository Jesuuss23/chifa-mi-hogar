<p>Esta información explica qué datos se utilizan al comprar, crear una cuenta o comunicarte con Chifa Mi Hogar.</p>
@include('legal.contact')
<section class="space-y-3">
    <h2 class="text-xl font-bold">Datos y finalidades</h2>
    <ul class="list-disc space-y-2 pl-5">
        <li>Cuenta: nombre, correo y contraseña protegida mediante hash, para identificarte y mostrar tus pedidos.</li>
        <li>Pedidos: nombre, teléfono, correo si lo proporcionas, dirección, referencia, coordenadas, productos, importes, notas y datos de pago, para preparar, cobrar y entregar tu compra.</li>
        <li>Comprobantes y comunicaciones: información que envías para verificar pagos y resolver consultas.</li>
        <li>Valoraciones: puntuación y comentario que decidas enviar sobre tus pedidos.</li>
        <li>Reparto: ubicación del repartidor mientras utiliza el seguimiento, para coordinar la entrega y mostrar su avance.</li>
        <li>Seguridad: sesión, dirección IP y datos técnicos necesarios para gestionar el acceso y prevenir abuso.</li>
    </ul>
    <p>Puedes comprar como invitado. Los campos marcados como obligatorios se necesitan para la función solicitada; sin ellos puede no ser posible registrar la cuenta o entregar el pedido. El permiso del navegador para usar tu ubicación se solicita al utilizar esa función; también puedes seleccionar el destino en el mapa.</p>
</section>
<section class="space-y-3">
    <h2 class="text-xl font-bold">Acceso a los datos y servicios externos</h2>
    <p>El personal autorizado y el repartidor asignado acceden a los datos necesarios para gestionar el pedido. Quien tenga tu enlace de seguimiento puede consultar la información que muestra: no lo compartas públicamente.</p>
    <ul class="list-disc space-y-2 pl-5">
        <li>WhatsApp / Meta: cuando está configurado, el negocio envía avisos de pedidos a sus destinatarios autorizados. Estos avisos pueden contener nombre, teléfono, dirección, coordenadas, detalle del pedido y notas. También puedes abrir WhatsApp para contactar o enviar comprobantes.</li>
        <li>Geoapify: el servidor consulta rutas con coordenadas de origen y destino cuando se necesita el recorrido del reparto.</li>
        <li>OpenStreetMap: tu navegador solicita imágenes del mapa. El proveedor recibe datos de conexión, como la IP, y la zona del mapa solicitada.</li>
        <li>Google Places: el servidor consulta reseñas del negocio cuando la función está configurada. Abrir enlaces de Google Maps o reseñas conecta tu navegador con Google.</li>
        @if(config('turnstile.enabled', false))
            <li>Cloudflare Turnstile: comprueba solicitudes de registro con datos técnicos del navegador y una verificación del servidor, incluida la IP. Esta función se utiliza para prevenir registros automatizados.</li>
        @endif
    </ul>
    <p>Estos proveedores pueden tratar información fuera del Perú. Consulta también sus políticas: <a class="underline" rel="noopener noreferrer" href="https://www.whatsapp.com/legal/privacy-policy">WhatsApp</a>, <a class="underline" rel="noopener noreferrer" href="https://www.geoapify.com/privacy-policy/">Geoapify</a>, <a class="underline" rel="noopener noreferrer" href="https://osmfoundation.org/wiki/Privacy_Policy">OpenStreetMap</a> y <a class="underline" rel="noopener noreferrer" href="https://policies.google.com/privacy">Google</a>@if(config('turnstile.enabled', false)), <a class="underline" rel="noopener noreferrer" href="https://www.cloudflare.com/privacypolicy/">Cloudflare</a>@endif.</p>
    <h3 class="font-bold">Alojamiento y otros encargados</h3>
    <p class="whitespace-pre-line">{{ $settings->privacy_hosting ?: 'Pendiente de completar en el borrador.' }}</p>
</section>
<section class="space-y-3">
    <h2 class="text-xl font-bold">Bancos de datos y conservación</h2>
    <p class="whitespace-pre-line">{{ $settings->privacy_database ?: 'Pendiente de completar en el borrador.' }}</p>
    <p class="whitespace-pre-line">{{ $settings->privacy_retention ?: 'Pendiente de completar en el borrador.' }}</p>
</section>
<section class="space-y-3">
    <h2 class="text-xl font-bold">Tus derechos</h2>
    <p>Puedes solicitar acceso, rectificación, cancelación u oposición al tratamiento de tus datos y, cuando corresponda, retirar tu consentimiento. Escribe a {{ $settings->privacy_email }} indicando tu solicitud. Podremos pedir la información necesaria para verificar tu identidad.</p>
    <p>También puedes acudir a la Autoridad Nacional de Protección de Datos Personales. La información sobre cookies se encuentra en la página correspondiente. No se solicita autorización para publicidad al realizar un pedido.</p>
</section>
