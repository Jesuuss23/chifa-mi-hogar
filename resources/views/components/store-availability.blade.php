@php
    $availabilitySettings = \App\Models\StoreSetting::current();
@endphp

@if(!$availabilitySettings->accepting_orders)
    <section id="store-closed-notice" role="status" aria-labelledby="store-closed-title"
             class="catalog-surface border-b border-border bg-accent px-4 py-4 text-foreground">
        <div class="mx-auto max-w-7xl">
            <h2 id="store-closed-title" class="font-bold">Por ahora estamos fuera de servicio</h2>
            <p class="mt-1 text-sm">{{ $availabilitySettings->closedMessage() }}</p>
            <p class="mt-2 text-sm">
                Puedes seguir viendo la carta y preparando tu carrito.
                Los pedidos ya registrados mantienen su seguimiento.
            </p>
        </div>
    </section>
@endif
