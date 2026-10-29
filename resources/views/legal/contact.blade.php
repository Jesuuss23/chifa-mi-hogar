<dl class="space-y-2 rounded-none bg-card p-5">
    <div><dt class="inline font-semibold">Responsable: </dt><dd class="inline">{{ $settings->business_owner }}</dd></div>
    <div><dt class="inline font-semibold">Domicilio: </dt><dd class="inline">{{ $settings->legal_address ?: 'Pendiente de completar en el borrador.' }}</dd></div>
    @if($settings->business_ruc)
        <div><dt class="inline font-semibold">RUC: </dt><dd class="inline">{{ $settings->business_ruc }}</dd></div>
    @elseif($preview)
        <div>RUC pendiente de confirmar.</div>
    @endif
    <div><dt class="inline font-semibold">Correo: </dt><dd class="inline"><a class="underline" href="mailto:{{ $settings->privacy_email }}">{{ $settings->privacy_email }}</a></dd></div>
    <div><dt class="inline font-semibold">Sitio web: </dt><dd class="inline">{{ $settings->website_url }}</dd></div>
</dl>
