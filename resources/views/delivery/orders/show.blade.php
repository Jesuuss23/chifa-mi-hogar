
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">

    <title>
        Entrega {{ $order->code }} | Chifa Mi Hogar
    </title>

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

@php
    /*
    |--------------------------------------------------------------------------
    | UBICACIÓN DEL CLIENTE
    |--------------------------------------------------------------------------
    |
    | IMPORTANTE:
    | Usamos únicamente las coordenadas de entrega.
    | Nunca las coordenadas GPS del repartidor como destino.
    |
    */

    $latitude = $order->delivery_latitude;
    $longitude = $order->delivery_longitude;

    $hasDestination =
        $latitude !== null &&
        $longitude !== null &&
        is_numeric($latitude) &&
        is_numeric($longitude) &&
        (float) $latitude >= -90 &&
        (float) $latitude <= 90 &&
        (float) $longitude >= -180 &&
        (float) $longitude <= 180;

    $destinationCoordinates = null;

    $googleLocationUrl = null;
    $googleNavigationUrl = null;

    if ($hasDestination) {

        $destinationCoordinates = sprintf(
            '%.7F,%.7F',
            (float) $latitude,
            (float) $longitude
        );

        /*
        |--------------------------------------------------------------------------
        | VER PUNTO EXACTO
        |--------------------------------------------------------------------------
        |
        | Mismo formato utilizado en el administrador.
        |
        */

        $googleLocationUrl =
            'https://www.google.com/maps/search/?'
            . http_build_query(
                [
                    'api' => 1,
                    'query' => $destinationCoordinates,
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            );

        /*
        |--------------------------------------------------------------------------
        | INICIAR NAVEGACIÓN
        |--------------------------------------------------------------------------
        |
        | Destino: coordenadas del CLIENTE.
        | Origen: ubicación actual del repartidor.
        |
        */

        $googleNavigationUrl =
            'https://www.google.com/maps/dir/?'
            . http_build_query(
                [
                    'api' => 1,
                    'destination' => $destinationCoordinates,
                    'travelmode' => 'driving',
                    'dir_action' => 'navigate',
                ],
                '',
                '&',
                PHP_QUERY_RFC3986
            );
    }

    $orderStatusLabel = match ($order->status) {
        'pending' => 'Pendiente',
        'confirmed' => 'Confirmado',
        'preparing' => 'En preparación',
        'on_the_way' => 'En camino',
        'delivered' => 'Entregado',
        'cancelled' => 'Cancelado',
        default => $order->status,
    };
@endphp

<body class="min-h-screen bg-[#F7F5F2] text-slate-900 antialiased">

<main class="mx-auto max-w-2xl space-y-5 px-4 py-6 sm:py-8">

    {{-- ===============================================
         ENCABEZADO
    ================================================ --}}

    <header class="space-y-4">

        <a
            href="{{ route('delivery.orders.index') }}"
            class="inline-flex items-center gap-2 text-sm font-semibold text-[#982E26]"
        >
            ← Mis entregas
        </a>

        <div class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">

            <p class="text-xs font-bold uppercase tracking-wider text-[#982E26]">
                Chifa Mi Hogar · Delivery
            </p>

            <h1 class="mt-2 break-all text-xl font-black text-gray-950 sm:text-2xl">
                Pedido {{ $order->code }}
            </h1>

            <div class="mt-3 flex flex-wrap items-center gap-2">

                <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                    {{ $orderStatusLabel }}
                </span>

                <span class="text-xs text-gray-500">
                    Entrega asignada
                </span>

            </div>

        </div>

    </header>

    {{-- ===============================================
         DATOS DEL CLIENTE
    ================================================ --}}

    <section class="space-y-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">

        <div>
            <h2 class="text-lg font-black text-gray-950">
                Datos del cliente
            </h2>

            <p class="mt-1 text-xs text-gray-500">
                Información registrada al confirmar el pedido.
            </p>
        </div>

        <dl class="grid gap-4 border-t border-gray-100 pt-5 text-sm sm:grid-cols-2">

            <div>
                <dt class="text-xs font-medium text-gray-500">
                    Nombre
                </dt>

                <dd class="mt-1 font-bold text-gray-900">
                    {{ $order->customer_name }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500">
                    Teléfono
                </dt>

                <dd class="mt-1">
                    <a
                        href="tel:{{ preg_replace('/\D+/', '', (string) $order->customer_phone) }}"
                        class="font-bold text-blue-700 hover:underline"
                    >
                        {{ $order->customer_phone }}
                    </a>
                </dd>
            </div>

            <div class="sm:col-span-2">
                <dt class="text-xs font-medium text-gray-500">
                    Dirección de entrega
                </dt>

                <dd class="mt-1 font-semibold leading-6 text-gray-900">
                    {{ $order->delivery_address }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500">
                    Referencia
                </dt>

                <dd class="mt-1 text-gray-800">
                    {{ $order->delivery_reference ?: 'Sin referencia' }}
                </dd>
            </div>

            <div>
                <dt class="text-xs font-medium text-gray-500">
                    Distancia aproximada
                </dt>

                <dd class="mt-1 font-semibold text-gray-800">
                    @if($order->delivery_distance_km !== null)
                        {{ number_format(
                            (float) $order->delivery_distance_km,
                            2
                        ) }} km
                    @else
                        Sin calcular
                    @endif
                </dd>
            </div>

        </dl>

        {{-- OBSERVACIONES DEL CLIENTE --}}

        @if($order->customer_notes)

            <div class="rounded-xl bg-amber-50 p-4">

                <p class="text-xs font-bold text-amber-900">
                    Observaciones
                </p>

                <p class="mt-2 text-sm leading-6 text-amber-900">
                    {{ $order->customer_notes }}
                </p>

            </div>

        @endif

    </section>

    {{-- ===============================================
         UBICACIÓN EXACTA DEL PEDIDO
    ================================================ --}}

    <section class="space-y-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">

        <div>

            <h2 class="text-lg font-black text-gray-950">
                Ubicación de entrega
            </h2>

            <p class="mt-1 text-sm leading-6 text-gray-500">
                Este destino se obtiene directamente de las
                coordenadas registradas en el pedido.
            </p>

        </div>

        @if($hasDestination)

            {{-- ABRIR MARCADOR EXACTO --}}

            <a
                href="{{ $googleLocationUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl border border-blue-600 bg-white px-4 py-3 text-center text-sm font-bold text-blue-700 transition hover:bg-blue-50"
            >
                <span aria-hidden="true">📍</span>
                Ver punto exacto en Google Maps
            </a>

            {{-- ABRIR NAVEGACIÓN --}}

            <a
                href="{{ $googleNavigationUrl }}"
                target="_blank"
                rel="noopener noreferrer"
                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-center text-sm font-bold text-white transition hover:bg-blue-700"
            >
                <span aria-hidden="true">🛵</span>
                Iniciar navegación
            </a>

            <div class="rounded-xl bg-[#F7F8FA] p-4">

                <p class="text-xs font-bold text-gray-800">
                    Coordenadas del cliente
                </p>

                <p class="mt-2 break-all font-mono text-sm font-semibold text-blue-700">
                    {{ $destinationCoordinates }}
                </p>

                <div class="mt-3 border-t border-gray-200 pt-3">

                    <p class="text-xs font-bold text-gray-700">
                        Código del pedido
                    </p>

                    <p class="mt-1 break-all text-xs text-gray-600">
                        {{ $order->code }}
                    </p>

                    <p class="mt-3 text-xs font-bold text-gray-700">
                        ID del pedido
                    </p>

                    <p class="mt-1 break-all font-mono text-xs text-gray-500">
                        {{ $order->id }}
                    </p>

                </div>

            </div>

            <div class="rounded-xl border border-amber-100 bg-amber-50 p-4">

                <p class="text-xs leading-6 text-amber-900">
                    <strong>Importante:</strong>
                    «Ver punto exacto» abre el marcador del cliente.
                    «Iniciar navegación» permite que Google Maps
                    calcule una ruta desde tu ubicación actual.
                    La calle de acceso puede ser diferente al punto marcado.
                </p>

            </div>

        @else

            <div class="rounded-xl bg-amber-50 p-4">

                <p class="text-sm font-bold text-amber-900">
                    Ubicación GPS no disponible
                </p>

                <p class="mt-2 text-xs leading-6 text-amber-800">
                    Este pedido no cuenta con coordenadas
                    válidas. Verifica la dirección y referencia
                    antes de realizar la entrega.
                </p>

            </div>

        @endif

    </section>

    {{-- ===============================================
         INFORMACIÓN DEL PAGO
    ================================================ --}}

    <section class="rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">

        <h2 class="text-lg font-black text-gray-950">
            Información de pago
        </h2>

        <div class="mt-4 space-y-3 text-sm">

            <div class="flex items-center justify-between gap-3">

                <span class="text-gray-500">
                    Método
                </span>

                <strong>
                    {{ match ($order->payment_method) {
                        'cash' => 'Efectivo',
                        'yape' => 'Yape',
                        'plin' => 'Plin',
                        default => strtoupper((string) $order->payment_method),
                    } }}
                </strong>

            </div>

            <div class="flex items-center justify-between gap-3">

                <span class="text-gray-500">
                    Total del pedido
                </span>

                <strong class="text-lg text-[#982E26]">
                    S/ {{ number_format((float) $order->total, 2) }}
                </strong>

            </div>

            @if($order->payment_method === 'cash')

                <div class="space-y-2 border-t border-gray-100 pt-3">

                    <div class="flex justify-between gap-3">
                        <span class="text-gray-500">
                            El cliente pagará con
                        </span>

                        <strong>
                            S/ {{ number_format(
                                (float) $order->cash_received_amount,
                                2
                            ) }}
                        </strong>
                    </div>

                    <div class="flex justify-between gap-3">

                        <span class="text-gray-500">
                            Vuelto
                        </span>

                        <strong class="text-[#982E26]">
                            S/ {{ number_format(
                                (float) $order->cash_change_amount,
                                2
                            ) }}
                        </strong>

                    </div>

                </div>

            @endif

        </div>

    </section>

    {{-- ===============================================
         SEGUIMIENTO GPS DEL REPARTIDOR
         SE CONSERVA EL FLUJO ACTUAL
    ================================================ --}}

    <section class="space-y-5 rounded-2xl border border-gray-100 bg-white p-5 shadow-sm">

        <div>

            <h2 class="text-lg font-black text-gray-950">
                Seguimiento GPS
            </h2>

            <p class="mt-2 text-sm leading-6 text-gray-500">
                Comparte tu ubicación durante el reparto para que
                el cliente pueda seguir el avance de su pedido.
            </p>

        </div>

        <div
            id="gps-status"
            role="status"
            aria-live="polite"
            class="rounded-xl bg-slate-100 p-4 text-sm leading-6 text-slate-700"
        >
            GPS desactivado.
        </div>

        @if($order->status === 'on_the_way')

            <div class="space-y-3">

                <button
                    type="button"
                    id="gps-start"
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-green-600 px-4 py-3 font-bold text-white transition hover:bg-green-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Iniciar seguimiento GPS
                </button>

                <button
                    type="button"
                    id="gps-stop"
                    disabled
                    class="flex min-h-12 w-full items-center justify-center rounded-xl bg-red-600 px-4 py-3 font-bold text-white transition hover:bg-red-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Detener seguimiento
                </button>

            </div>

        @else

            <div class="rounded-xl bg-amber-50 p-4">

                <p class="text-sm leading-6 text-amber-800">
                    El seguimiento GPS estará disponible
                    cuando el pedido esté en camino.
                </p>

            </div>

        @endif

    </section>

</main>

{{-- ===============================================
     JAVASCRIPT DE SEGUIMIENTO GPS
=============================================== --}}

@if($order->status === 'on_the_way')

<script>
(() => {
    'use strict';

    const startButton = document.getElementById('gps-start');
    const stopButton = document.getElementById('gps-stop');
    const status = document.getElementById('gps-status');

    const csrf = @json(csrf_token());

    const urls = {
        start: @json(route('delivery.gps.start', $order)),
        update: @json(route('delivery.gps.update', $order)),
        stop: @json(route('delivery.gps.stop', $order))
    };

    let watchId = null;
    let active = false;
    let sending = false;
    let lastSentAt = 0;

    function showStatus(message) {
        status.textContent = message;
    }

    async function post(url, data = {}) {

        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',

            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrf
            },

            body: JSON.stringify(data)
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(
                result.message ||
                'Error de comunicación con el servidor.'
            );
        }

        return result;
    }

    function resetButtons() {
        startButton.disabled = false;
        stopButton.disabled = true;
    }

    async function stopTracking() {

        active = false;

        if (watchId !== null) {
            navigator.geolocation.clearWatch(watchId);
            watchId = null;
        }

        stopButton.disabled = true;

        try {

            await post(urls.stop);

            showStatus(
                'Seguimiento GPS detenido.'
            );

        } catch (error) {

            showStatus(
                'No se pudo confirmar la detención: ' +
                error.message
            );

        } finally {
            resetButtons();
        }
    }

    async function sendPosition(position) {

        if (!active || sending) {
            return;
        }

        const now = Date.now();

        if (now - lastSentAt < 5000) {
            return;
        }

        const {
            latitude,
            longitude,
            accuracy
        } = position.coords;

        if (accuracy > 100) {
            showStatus(
                'Esperando una ubicación GPS más precisa...'
            );
            return;
        }

        sending = true;

        try {

            await post(urls.update, {
                latitude,
                longitude,
                accuracy
            });

            lastSentAt = Date.now();

            showStatus(
                'GPS activo. Ubicación compartida a las ' +
                new Date().toLocaleTimeString('es-PE')
            );

        } catch (error) {

            showStatus(
                'Error GPS: ' + error.message
            );

        } finally {
            sending = false;
        }
    }

    startButton.addEventListener('click', async () => {

        if (!navigator.geolocation) {
            showStatus(
                'Este dispositivo no admite geolocalización.'
            );
            return;
        }

        if (!window.isSecureContext) {
            showStatus(
                'El GPS requiere una conexión HTTPS.'
            );
            return;
        }

        startButton.disabled = true;

        showStatus(
            'Solicitando permiso para utilizar el GPS...'
        );

        try {

            await post(urls.start);

            active = true;
            stopButton.disabled = false;

            watchId = navigator.geolocation.watchPosition(
                sendPosition,

                error => {

                    showStatus(
                        'No se pudo obtener el GPS: ' +
                        error.message
                    );

                    if (error.code === 1) {
                        stopTracking();
                    }
                },

                {
                    enableHighAccuracy: true,
                    maximumAge: 3000,
                    timeout: 15000
                }
            );

        } catch (error) {

            resetButtons();
            showStatus(error.message);
        }
    });

    stopButton.addEventListener(
        'click',
        stopTracking
    );

})();
</script>

@endif

</body>
</html>
