@php
    $hasOldProfileDelivery = collect(['delivery_address', 'delivery_reference', 'delivery_latitude', 'delivery_longitude'])
        ->contains(fn ($field) => session()->hasOldInput($field));
@endphp
<section class="mt-8 border border-border bg-card p-5 sm:p-6" aria-labelledby="profile-title">
    <h2 id="profile-title" class="text-xl font-bold">Mis datos y dirección de entrega</h2>
    <p class="mt-2 text-sm text-foreground/75">
        Completa tus datos para usarlos en tus próximos pedidos. Podrás cambiarlos para cada entrega.
        Guardar aquí actualiza tu cuenta; no modifica pedidos ya realizados.
    </p>

    @if(\App\Models\StoreSetting::current()->legalPagesAvailable())
        <p class="mt-2 text-sm"><a href="{{ route('legal.privacy') }}" class="underline">Cómo usamos tus datos</a></p>
    @endif

    @if(session('profile_saved'))
        <p role="status" class="mt-4 bg-green-50 p-3 text-green-800">Tus datos se guardaron correctamente.</p>
    @endif
    @if($errors->any())
        <div role="alert" class="mt-4 bg-destructive/10 p-3 text-destructive">
            <ul class="list-inside list-disc">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form id="customer-profile-form" method="POST" action="{{ route('customer.profile.update') }}" class="mt-5 space-y-4">
        @csrf
        @method('PUT')
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="profile-name" class="block text-sm font-semibold">Nombre completo</label>
                <input id="profile-name" name="name" autocomplete="name" required maxlength="150"
                       value="{{ old('name', auth()->user()->name) }}"
                       class="mt-1 w-full border border-input bg-background p-3">
            </div>
            <div>
                <label for="profile-phone" class="block text-sm font-semibold">Celular</label>
                <input id="profile-phone" name="phone" type="tel" inputmode="numeric" autocomplete="tel-national"
                       required pattern="9[0-9]{8}" maxlength="9"
                       value="{{ old('phone', $profile?->phone) }}"
                       class="mt-1 w-full border border-input bg-background p-3">
            </div>
        </div>
        <p class="text-sm text-foreground/75">Correo de tu cuenta: {{ auth()->user()->email }}</p>

        <div>
            <label for="profile-address" class="block text-sm font-semibold">Dirección predeterminada (opcional)</label>
            <input id="profile-address" name="delivery_address" autocomplete="street-address" maxlength="500"
                   value="{{ old('delivery_address', $hasOldProfileDelivery ? null : $profile?->delivery_address) }}"
                   class="mt-1 w-full border border-input bg-background p-3">
        </div>
        <div>
            <label for="profile-reference" class="block text-sm font-semibold">Referencia (opcional)</label>
            <input id="profile-reference" name="delivery_reference" maxlength="500"
                   value="{{ old('delivery_reference', $hasOldProfileDelivery ? null : $profile?->delivery_reference) }}"
                   class="mt-1 w-full border border-input bg-background p-3">
        </div>

        <p class="text-sm text-foreground/75">
            Para guardar una dirección, escribe el texto y marca su punto exacto en el mapa.
            Si cambias el texto, vuelve a marcar la ubicación.
        </p>
        <div class="flex flex-wrap gap-3">
            <button id="profile-use-location" type="button" class="border border-border px-4 py-3 font-semibold">
                Usar mi ubicación actual
            </button>
            <button id="profile-clear-address" type="button" class="border border-border px-4 py-3">
                Quitar dirección guardada
            </button>
        </div>
        <div id="profile-map" class="h-72 w-full border border-border" aria-label="Mapa para elegir tu ubicación"></div>
        <input type="hidden" id="profile-latitude" name="delivery_latitude"
               value="{{ old('delivery_latitude', $hasOldProfileDelivery ? null : $profile?->delivery_latitude) }}">
        <input type="hidden" id="profile-longitude" name="delivery_longitude"
               value="{{ old('delivery_longitude', $hasOldProfileDelivery ? null : $profile?->delivery_longitude) }}">
        <p id="profile-location-status" role="status" aria-live="polite" class="text-sm text-foreground/75">
            Selecciona un punto si deseas guardar una dirección.
        </p>
        <noscript><p class="text-sm text-destructive">Activa JavaScript para seleccionar o cambiar la ubicación del mapa.</p></noscript>

        <button type="submit" class="bg-primary px-5 py-3 font-bold text-primary-foreground hover:bg-primary/90">
            Guardar mis datos
        </button>
    </form>
</section>
