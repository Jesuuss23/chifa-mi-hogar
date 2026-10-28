<p>Las cookies son pequeños datos que el navegador guarda para mantener funciones del sitio. Esta aplicación utiliza cookies necesarias para la sesión y la seguridad.</p>
<div class="overflow-x-auto">
    <table class="w-full border-collapse text-left text-sm">
        <thead><tr><th class="border p-3">Cookie propia</th><th class="border p-3">Finalidad</th><th class="border p-3">Duración configurada</th></tr></thead>
        <tbody>
            <tr>
                <td class="border p-3 break-all">{{ config('session.cookie') }}</td>
                <td class="border p-3">Mantener sesión, carrito y confirmación del pedido.</td>
                <td class="border p-3">
                    @if(config('session.expire_on_close'))
                        Hasta cerrar el navegador; la sesión tiene además un límite de inactividad de {{ (int) config('session.lifetime') }} minutos.
                    @else
                        {{ (int) config('session.lifetime') }} minutos, renovados al utilizar el sitio.
                    @endif
                </td>
            </tr>
            <tr>
                <td class="border p-3">XSRF-TOKEN</td>
                <td class="border p-3">Ayudar a proteger formularios y solicitudes frente a falsificación.</td>
                <td class="border p-3">{{ (int) config('session.lifetime') }} minutos, renovados con las respuestas del sitio.</td>
            </tr>
        </tbody>
    </table>
</div>
<section class="space-y-3">
    <h2 class="text-xl font-bold">Cookies opcionales</h2>
    <p>No hemos incorporado herramientas de analítica ni publicidad que utilicen cookies opcionales en esta aplicación. Por eso no mostramos botones de aceptación o rechazo sin una función real.</p>
    <p>Puedes borrar o bloquear cookies desde tu navegador. Si bloqueas las necesarias, el carrito, el acceso a tu cuenta y la compra pueden dejar de funcionar. No necesitas aceptar publicidad para comprar.</p>
</section>
<section class="space-y-3">
    <h2 class="text-xl font-bold">Mapas y servicios externos</h2>
    <p>Los mapas solicitan imágenes a OpenStreetMap desde tu navegador. Las imágenes pueden guardarse en la caché del navegador, que es distinta de las cookies. El proveedor recibe datos de conexión y de la zona consultada.</p>
    @if(config('turnstile.enabled', false))
        <p>El registro utiliza Cloudflare Turnstile para seguridad. Al abrirlo, el navegador se comunica con Cloudflare. Puedes consultar su <a class="underline" rel="noopener noreferrer" href="https://www.cloudflare.com/privacypolicy/">política de privacidad</a>.</p>
    @endif
    <p>Al abrir enlaces de WhatsApp, Google u otros servicios, se aplican también sus políticas. Encontrarás más información sobre estos destinatarios en Privacidad.</p>
</section>
<p>Para consultas, escribe a {{ $settings->privacy_email }}.</p>
