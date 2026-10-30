@extends('layouts.admin')

@section('title', 'Configuración general')
@section('page-title', 'Configuración general')

@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-5xl">

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-foreground">
            Configuración general
        </h1>
        <p class="mt-2 text-sm text-foreground/75">
            Administra pagos, delivery, ubicación y atención de Chifa Mi Hogar.
        </p>
    </div>

    @if($errors->any())
        <div role="alert" class="mb-6 rounded-none border border-destructive/30 bg-destructive/10 p-4 text-destructive">
            <p class="font-semibold">Revisa los siguientes campos:</p>
            <ul class="mt-2 list-inside list-disc text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('admin.settings.brand')
    @include('admin.settings.legal')

    <form method="POST"
          action="{{ route('admin.settings.update') }}"
          enctype="multipart/form-data"
          class="space-y-6">

        @csrf
        @method('PUT')

        {{-- PAGOS --}}
        <section class="rounded-none border bg-card p-6 shadow-storefront">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-foreground">
                    📱 Configuración de Yape / Plin
                </h2>
                <p class="mt-1 text-sm text-foreground/75">
                    Información que verá el cliente para realizar su pago.
                </p>
            </div>

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="yape_number"
                           class="mb-2 block text-sm font-semibold">
                        Número de Yape
                    </label>
                    <input id="yape_number"
                           name="yape_number"
                           type="text"
                           inputmode="numeric"
                           maxlength="9"
                           value="{{ old('yape_number', $settings->yape_number) }}"
                           placeholder="987654321"
                           class="w-full rounded-none border border-input p-3">
                </div>

                <div>
                    <label for="plin_number"
                           class="mb-2 block text-sm font-semibold">
                        Número de Plin
                    </label>
                    <input id="plin_number"
                           name="plin_number"
                           type="text"
                           inputmode="numeric"
                           maxlength="9"
                           value="{{ old('plin_number', $settings->plin_number) }}"
                           placeholder="987654321"
                           class="w-full rounded-none border border-input p-3">
                </div>

                <div class="md:col-span-2">
                    <label for="payment_holder"
                           class="mb-2 block text-sm font-semibold">
                        Nombre del titular
                    </label>
                    <input id="payment_holder"
                           name="payment_holder"
                           type="text"
                           maxlength="150"
                           value="{{ old('payment_holder', $settings->payment_holder) }}"
                           placeholder="Nombre que aparece en Yape o Plin"
                           class="w-full rounded-none border border-input p-3">
                </div>
            </div>

            <div class="mt-6">
                <label for="payment_qr"
                       class="mb-3 block text-sm font-semibold">
                    Imagen del QR oficial
                </label>

                @if($settings->paymentQr)
                    <div class="mb-4">
                        <img
                            src="{{ $settings->paymentQr->detailUrl() }}"
                            alt="QR de pago actual"
                            class="h-48 w-48 max-w-full rounded-none border bg-white object-contain p-2"
                        >
                        <p class="mt-2 text-xs text-green-700">
                            QR configurado actualmente.
                        </p>
                    </div>
                @else
                    <div class="mb-4 flex h-40 w-40 items-center justify-center rounded-none border border-dashed bg-muted text-center text-sm text-foreground/75">
                        Sin QR configurado
                    </div>
                @endif

                <input id="payment_qr"
                       name="payment_qr"
                       type="file"
                       accept="image/jpeg,image/png,image/webp"
                       class="block w-full rounded-none border border-input p-3 text-sm">

                <p class="mt-2 text-xs text-foreground/75">
                    JPG, PNG o WebP. Máximo 5 MB.
                    Si no seleccionas otra imagen, se conserva la actual.
                </p>
            </div>
        </section>

        {{-- DELIVERY --}}
        <section class="rounded-none border bg-card p-6 shadow-storefront">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-foreground">
                    🛵 Delivery y ubicación de la cocina
                </h2>
                <p class="mt-1 text-sm text-foreground/75">
                    Configura el punto de origen y las tarifas de reparto.
                </p>
            </div>

            <div class="space-y-5">
                <div>
                    <label for="orders_whatsapp"
                           class="mb-2 block text-sm font-semibold">
                        WhatsApp comercial
                    </label>
                    <input id="orders_whatsapp"
                           name="orders_whatsapp"
                           type="tel"
                           value="{{ old('orders_whatsapp', $settings->orders_whatsapp) }}"
                           placeholder="51987654321"
                           class="w-full rounded-none border border-input p-3">

                    <p class="mt-1 text-xs text-foreground/75">
                        Número que se mostrará como contacto comercial.
                        No es necesariamente el número técnico de Meta.
                    </p>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label for="free_delivery_km"
                               class="mb-2 block text-sm font-semibold">
                            Kilómetros gratuitos de cobertura *
                        </label>
                        <input id="free_delivery_km"
                               name="free_delivery_km"
                               type="number"
                               min="0"
                               step="0.01"
                               required
                               value="{{ old('free_delivery_km', $settings->free_delivery_km) }}"
                               class="w-full rounded-none border border-input p-3">

                        <p class="mt-1 text-xs text-foreground/75">
                            Distancia cubierta sin costo adicional.
                        </p>
                    </div>

                    <div>
                        <label for="additional_cost_per_km"
                               class="mb-2 block text-sm font-semibold">
                            Costo por kilómetro adicional (S/) *
                        </label>
                        <input id="additional_cost_per_km"
                               name="additional_cost_per_km"
                               type="number"
                               min="0"
                               step="0.01"
                               required
                               value="{{ old('additional_cost_per_km', $settings->additional_cost_per_km) }}"
                               class="w-full rounded-none border border-input p-3">

                        <p class="mt-1 text-xs text-foreground/75">
                            Tarifa por cada kilómetro que exceda la cobertura gratuita.
                        </p>
                    </div>

                    <div>
                        <label for="kitchen_latitude"
                               class="mb-2 block text-sm font-semibold">
                            Latitud de la cocina *
                        </label>
                        <input id="kitchen_latitude"
                               name="kitchen_latitude"
                               type="number"
                               step="any"
                               min="-90"
                               max="90"
                               required
                               value="{{ old('kitchen_latitude', $settings->kitchen_latitude) }}"
                               placeholder="-11.979883"
                               class="w-full rounded-none border border-input p-3">
                    </div>

                    <div>
                        <label for="kitchen_longitude"
                               class="mb-2 block text-sm font-semibold">
                            Longitud de la cocina *
                        </label>
                        <input id="kitchen_longitude"
                               name="kitchen_longitude"
                               type="number"
                               step="any"
                               min="-180"
                               max="180"
                               required
                               value="{{ old('kitchen_longitude', $settings->kitchen_longitude) }}"
                               placeholder="-76.993542"
                               class="w-full rounded-none border border-input p-3">
                    </div>
                </div>

                <div class="rounded-none bg-accent p-4 text-sm text-foreground">
                    <p class="font-semibold">¿Cómo obtener las coordenadas?</p>
                    <p class="mt-1">
                        Abre Google Maps, selecciona la ubicación exacta de tu cocina
                        y copia las coordenadas que aparecen en el mapa.
                    </p>

                    @if($settings->kitchen_latitude !== null && $settings->kitchen_longitude !== null)
                        <a
                            href="https://www.google.com/maps/search/?api=1&query={{ $settings->kitchen_latitude }},{{ $settings->kitchen_longitude }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="mt-3 inline-block font-semibold underline">
                            Ver ubicación guardada en Google Maps ↗
                        </a>
                    @endif
                </div>
            </div>
        </section>

        {{-- COMUNIDAD --}}
        <section class="rounded-none border bg-card p-6 shadow-storefront">
            <h2 class="mb-5 text-xl font-bold text-foreground">
                Comunidad de WhatsApp
            </h2>

            <label for="whatsapp_group_url"
                   class="mb-2 block text-sm font-semibold">
                Enlace del grupo o comunidad
            </label>
            <input id="whatsapp_group_url"
                   name="whatsapp_group_url"
                   type="url"
                   value="{{ old('whatsapp_group_url', $settings->whatsapp_group_url) }}"
                   placeholder="https://chat.whatsapp.com/..."
                   class="w-full rounded-none border border-input p-3">

            <p class="mt-2 text-xs text-foreground/75">
                Podremos mostrar este enlace cuando el local esté fuera de atención.
            </p>
        </section>

        {{-- ATENCIÓN --}}
        <section class="rounded-none border bg-card p-6 shadow-storefront">
            <h2 class="mb-5 text-xl font-bold text-foreground">
                🕒 Estado de atención
            </h2>

            <div class="rounded-none border border-border bg-muted p-5">
                <input type="hidden" name="accepting_orders" value="0">

                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox"
                           name="accepting_orders"
                           value="1"
                           @checked(old('accepting_orders', $settings->accepting_orders))
                           class="mt-1 h-5 w-5 rounded-none border-input accent-primary">

                    <span>
                        <span class="block font-semibold text-foreground">
                            Local abierto para recibir pedidos
                        </span>
                        <span class="mt-1 block text-sm text-foreground/75">
                            Si desactivas esta opción, la tienda seguirá visible,
                            pero no aceptará nuevas confirmaciones de pedidos.
                        </span>
                    </span>
                </label>
            </div>

            <div class="mt-5">
                <label for="closed_message"
                       class="mb-2 block text-sm font-semibold">
                    Mensaje cuando el local esté cerrado
                </label>
                <textarea id="closed_message"
                          name="closed_message"
                          rows="3"
                          maxlength="1000"
                          class="w-full rounded-none border border-input p-3"
                          placeholder="Por el momento no estamos recibiendo pedidos.">{{ old('closed_message', $settings->closed_message) }}</textarea>
            </div>
        </section>
        <div class="space-y-2">
            <label for="google_reviews_url" class="block font-semibold">
                Enlace para recibir reseñas de Google
            </label>

            <input
                type="url"
                id="google_reviews_url"
                name="google_reviews_url"
                value="{{ old('google_reviews_url', $settings->google_reviews_url) }}"
                placeholder="https://g.page/r/..."
                class="w-full rounded-none border-input"
            >

            <p class="text-sm text-foreground/75">
                Los clientes podrán abrir Google y dejar una reseña del negocio.
            </p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route('admin.products.index') }}"
               class="rounded-none border border-input px-6 py-3 font-medium text-foreground/75">
                Volver al catálogo
            </a>

            <button type="submit"
                    class="rounded-none bg-primary px-8 py-3 font-semibold text-primary-foreground transition hover:bg-primary/90">
                Guardar configuración
            </button>
        </div>
    </form>
</div>
@endsection