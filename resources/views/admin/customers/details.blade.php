<section aria-label="Datos del cliente">
    <div class="flex items-center gap-4">
        @if($hasAvatar)
            <img src="{{ route('admin.customers.avatar', $customer->id) }}" alt="Foto de perfil" width="80" height="80" class="h-20 w-20 rounded-full object-cover">
        @else
            <span class="flex h-20 w-20 shrink-0 items-center justify-center rounded-full bg-muted text-2xl" aria-hidden="true">{{ mb_substr($customer->name, 0, 1) }}</span>
        @endif
        <div class="min-w-0"><h3 class="break-words text-xl font-bold">{{ $customer->name }}</h3><p class="break-all">{{ $customer->email }}</p></div>
    </div>
    <dl class="customer-detail-fields mt-6">
        <div><dt>Teléfono</dt><dd>{{ $profile?->phone ?: 'No indicado' }}</dd></div>
        <div><dt>Correo</dt><dd>{{ $customer->email_verified_at ? 'Verificado' : 'Sin verificación registrada' }}</dd></div>
        <div><dt>Registro ({{ config('app.timezone') }})</dt><dd>{{ $customer->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'No disponible' }}</dd></div>
        <div><dt>Accesos sociales vinculados</dt><dd>{{ $customer->socialAccounts->pluck('provider')->map(fn ($provider) => ucfirst($provider))->implode(', ') ?: 'Ninguno' }}</dd></div>
    </dl>
    <h3 class="mt-6 text-lg font-bold">Dirección guardada en el perfil</h3>
    <p class="mt-2 text-sm text-foreground/75">El cliente puede elegir otra dirección para cada pedido. Esta ficha muestra sus preferencias actuales de entrega.</p>
    <dl class="customer-detail-fields mt-4">
        <div class="sm:col-span-2"><dt>Dirección</dt><dd>{{ $profile?->delivery_address ?: 'No indicada' }}</dd></div>
        <div class="sm:col-span-2"><dt>Referencia</dt><dd>{{ $profile?->delivery_reference ?: 'No indicada' }}</dd></div>
        <div><dt>Latitud</dt><dd>{{ $profile?->delivery_latitude ?? 'No indicada' }}</dd></div>
        <div><dt>Longitud</dt><dd>{{ $profile?->delivery_longitude ?? 'No indicada' }}</dd></div>
        <div><dt>Última actualización de entrega ({{ config('app.timezone') }})</dt><dd>{{ $profile?->updated_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'No disponible' }}</dd></div>
    </dl>
    @if($mapUrl)
        <a href="{{ $mapUrl }}" target="_blank" rel="noopener noreferrer" class="mt-4 inline-block rounded-lg border border-border px-4 py-3 underline">Ver ubicación en Google Maps ↗</a>
    @else
        <p class="mt-4 text-sm">Ubicación no configurada o incompleta.</p>
    @endif
</section>
