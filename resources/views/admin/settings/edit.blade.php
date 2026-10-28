@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-5xl px-4 py-8">

    <div class="mb-6">
        <h1 class="text-3xl font-bold text-slate-900">
            Configuración general
        </h1>
        <p class="mt-2 text-sm text-slate-500">
            Administra pagos, delivery, ubicación y atención de Chifa Mi Hogar.
        </p>
    </div>

    @if(session('success'))
        <div class="mb-6 rounded-xl border border-green-200 bg-green-50 p-4 text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="mb-6 rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
            <p class="font-semibold">Revisa los siguientes campos:</p>
            <ul class="mt-2 list-inside list-disc text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @include('admin.settings.brand')

    <form method="POST"
          action="{{ route('admin.settings.update') }}"
          enctype="multipart/form-data"
          class="space-y-6">

        @csrf
        @method('PUT')

        {{-- PAGOS --}}
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-slate-900">
                    📱 Configuración de Yape / Plin
                </h2>
                <p class="mt-1 text-sm text-slate-500">
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
                           class="w-full rounded-xl border border-slate-300 p-3">
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
                           class="w-full rounded-xl border border-slate-300 p-3">
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
                           class="w-full rounded-xl border border-slate-300 p-3">
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
                            class="h-48 w-48 rounded-xl border bg-white object-contain p-2"
                        >
                        <p class="mt-2 text-xs text-green-700">
                            QR configurado actualmente.
                        </p>
                    </div>
                @else
                    <div class="mb-4 flex h-40 w-40 items-center justify-center rounded-xl border border-dashed bg-slate-50 text-center text-sm text-slate-400">
                        Sin QR configurado
                    </div>
                @endif

                <input id="payment_qr"
                       name="payment_qr"
                       type="file"
                       accept="image/jpeg,image/png,image/webp"
                       class="block w-full rounded-xl border border-slate-300 p-3 text-sm">

                <p class="mt-2 text-xs text-slate-500">
                    JPG, PNG o WebP. Máximo 5 MB.
                    Si no seleccionas otra imagen, se conserva la actual.
                </p>
            </div>
        </section>

        {{-- DELIVERY --}}
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <div class="mb-6">
                <h2 class="text-xl font-bold text-slate-900">
                    🛵 Delivery y ubicación de la cocina
                </h2>
                <p class="mt-1 text-sm text-slate-500">
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
                           class="w-full rounded-xl border border-slate-300 p-3">

                    <p class="mt-1 text-xs text-slate-500">
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
                               class="w-full rounded-xl border border-slate-300 p-3">

                        <p class="mt-1 text-xs text-slate-500">
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
                               class="w-full rounded-xl border border-slate-300 p-3">

                        <p class="mt-1 text-xs text-slate-500">
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
                               class="w-full rounded-xl border border-slate-300 p-3">
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
                               class="w-full rounded-xl border border-slate-300 p-3">
                    </div>
                </div>

                <div class="rounded-xl bg-blue-50 p-4 text-sm text-blue-800">
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
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-xl font-bold text-slate-900">
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
                   class="w-full rounded-xl border border-slate-300 p-3">

            <p class="mt-2 text-xs text-slate-500">
                Podremos mostrar este enlace cuando el local esté fuera de atención.
            </p>
        </section>

        {{-- ATENCIÓN --}}
        <section class="rounded-2xl border bg-white p-6 shadow-sm">
            <h2 class="mb-5 text-xl font-bold text-slate-900">
                🕒 Estado de atención
            </h2>

            <div class="rounded-xl border border-slate-200 bg-slate-50 p-5">
                <input type="hidden" name="accepting_orders" value="0">

                <label class="flex cursor-pointer items-start gap-3">
                    <input type="checkbox"
                           name="accepting_orders"
                           value="1"
                           @checked(old('accepting_orders', $settings->accepting_orders))
                           class="mt-1 h-5 w-5 rounded border-slate-300 accent-orange-600">

                    <span>
                        <span class="block font-semibold text-slate-900">
                            Local abierto para recibir pedidos
                        </span>
                        <span class="mt-1 block text-sm text-slate-500">
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
                          class="w-full rounded-xl border border-slate-300 p-3"
                          placeholder="Por el momento no estamos recibiendo pedidos.">{{ old('closed_message', $settings->closed_message) }}</textarea>
            </div>
        </section>
        <div class="space-y-2">
            <label class="block font-semibold">
                Enlace para recibir reseñas de Google
            </label>

            <input
                type="url"
                name="google_reviews_url"
                value="{{ old('google_reviews_url', $settings->google_reviews_url) }}"
                placeholder="https://g.page/r/..."
                class="w-full rounded-lg border-gray-300"
            >

            <p class="text-sm text-gray-500">
                Los clientes podrán abrir Google y dejar una reseña del negocio.
            </p>
        </div>
        <div class="flex flex-wrap items-center justify-end gap-3">
            <a href="{{ route('admin.products.index') }}"
               class="rounded-xl border border-slate-300 px-6 py-3 font-medium text-slate-700">
                Volver al catálogo
            </a>

            <button type="submit"
                    class="rounded-xl bg-orange-600 px-8 py-3 font-semibold text-white transition hover:bg-orange-700">
                Guardar configuración
            </button>
        </div>
    </form>
</div>
@endsection