@extends('layouts.storefront')

@section('title', 'Seguimiento de pedido | Chifa Mi Hogar')

@push('head')
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
@endpush

@section('content')
@php
    $labels = [
        'pending' => 'Pedido recibido',
        'confirmed' => 'Pedido confirmado',
        'preparing' => 'Preparando tu comida',
        'on_the_way' => 'En camino',
        'delivered' => 'Pedido entregado',
    ];

    $statusLabels = $labels + [
        'cancelled' => 'Pedido cancelado',
    ];

    $steps = array_keys($labels);
@endphp

<section class="customer-surface mx-auto max-w-2xl px-4 py-10">

    <div class="rounded-none bg-card p-6 shadow-storefront sm:p-10">

        <div class="mb-7 text-center">
            <h1 class="text-3xl font-black text-foreground">
                Seguimiento de tu pedido
            </h1>

            <p class="mt-3 text-foreground/75">
                Consulta el avance de tu pedido en tiempo real.
            </p>
        </div>

        <div class="rounded-none bg-muted p-5">

            <p class="text-sm text-foreground/75">
                Código de pedido
            </p>

            <p class="mt-1 break-all font-bold text-foreground">
                {{ $order->code }}
            </p>

            <div class="mt-4 flex items-center justify-between gap-3">
                <span class="text-foreground/75">Total</span>

                <strong class="text-xl text-primary">
                    S/ {{ number_format((float) $order->total, 2) }}
                </strong>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3">
                <span class="text-foreground/75">Pago</span>

                <strong id="payment-status">
                    {{ $order->payment_status === 'verified'
                        ? 'Verificado'
                        : ($order->payment_status === 'rejected'
                            ? 'Rechazado'
                            : 'Pendiente') }}
                </strong>
            </div>
        </div>

        <div class="mt-6 rounded-none border border-border p-5">

            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold">
                    Estado del pedido
                </h2>

                <span
                    id="tracking-status"
                    class="rounded-none bg-green-100 px-3 py-1 text-sm font-bold text-green-800"
                >
                    {{ $statusLabels[$order->status] ?? 'En seguimiento' }}
                </span>
            </div>

            <div class="space-y-5">
                @foreach($labels as $key => $label)
                    <div
                        data-tracking-step="{{ $key }}"
                        class="flex items-center gap-3"
                    >
                        <span
                            data-step-icon
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-none bg-muted text-foreground/75"
                        >
                            ○
                        </span>

                        <span data-step-label class="text-foreground/75">
                            {{ $label }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div
                id="tracking-cancelled"
                class="mt-5 rounded-none bg-destructive/10 p-4 text-sm font-medium text-destructive"
                @if($order->status !== 'cancelled') hidden @endif
            >
                Este pedido fue cancelado.
            </div>

            <p id="tracking-sync-status" role="status" aria-live="polite" class="mt-6 text-xs text-foreground/75">
                Mostramos el último estado recibido.
            </p>
            <button id="tracking-retry" type="button" hidden class="mt-3 min-h-11 border border-border px-4 py-2 text-sm font-semibold">Actualizar seguimiento</button>
            <noscript><p class="mt-3 text-sm">Recarga esta página para consultar cambios del pedido.</p></noscript>
        </div>

        <div
            id="delivery-map-placeholder"
            class="mt-6 rounded-none border border-border bg-card p-5"
            hidden
        >
            <h2 class="text-lg font-bold text-foreground">
                Tu pedido está en camino
            </h2>

            <p class="mt-2 text-sm text-foreground/75">
                Tu pedido salió del restaurante.
                Sigue la ubicación del repartidor y su recorrido por calles.
            </p>

            <div
                id="delivery-route-map"
                class="mt-4 w-full overflow-hidden rounded-none bg-muted"
                style="height: 320px;"
                aria-label="Mapa de seguimiento del repartidor"
            ></div>

            <p
                id="delivery-route-info"
                class="mt-3 text-sm text-foreground/75"
                aria-live="polite"
            >
                Consultando ruta...
            </p>

            <p class="mt-2 text-xs text-foreground/75">
                La ubicación depende de la señal GPS y la conexión del repartidor.
            </p>
        </div>

        <a
            href="{{ url('/') }}"
            class="mt-6 block rounded-none border border-border px-5 py-3 text-center font-bold text-foreground"
        >
            Volver a la tienda
        </a>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const steps = [
        'pending',
        'confirmed',
        'preparing',
        'on_the_way',
        'delivered'
    ];

    const labels = {
        pending: 'Pedido recibido',
        confirmed: 'Pedido confirmado',
        preparing: 'Preparando tu comida',
        on_the_way: 'En camino',
        delivered: 'Pedido entregado',
        cancelled: 'Pedido cancelado'
    };

    const statusUrl = @json(route('orders.tracking.status', ['token' => $trackingToken]));
    const routeUrl = @json(
        route('orders.tracking.route', ['token' => $trackingToken])
    );

    let deliveryMap = null;
    let routeLoading = false;
    let driverMarker = null;
    let destinationMarker = null;
    let routeLayer = null;
    let mapInitialized = false;
    let lastRouteKey = null;
    let originalRouteCoordinates = null;
    let maximumProgressIndex = 0;


    @include('storefront.partials.tracking-gps-guard')
    let routeController = null;
    let pageLeaving = false;
    const gpsGuard = createTrackingGpsGuard({
        now: () => Date.now(), setTimeout, clearTimeout,
        notify(message) {
            const info = document.getElementById('delivery-route-info');
            if (info) info.textContent = message;
        },
        clear() {
            if (routeLayer) deliveryMap.removeLayer(routeLayer);
            if (driverMarker) deliveryMap.removeLayer(driverMarker);
            routeLayer = null;
            driverMarker = null;
            lastRouteKey = null;
            originalRouteCoordinates = null;
            maximumProgressIndex = 0;
        },
    });
    function suspendRoute() {
        gpsGuard.invalidate('Ubicación no disponible por ahora. Se consultará de nuevo al actualizar el seguimiento.');
        routeController?.abort();
    }
    window.addEventListener('pagehide', () => {
        pageLeaving = true;
        suspendRoute();
    });

    async function loadDeliveryMap() {
        if (routeLoading || pageLeaving || document.hidden || navigator.onLine === false || currentStatus !== 'on_the_way') return;

        const container = document.getElementById('delivery-route-map');
        const info = document.getElementById('delivery-route-info');

        if (!container || typeof window.L === 'undefined') {
            if (info) info.textContent = 'Mapa no disponible.';
            return;
        }

        routeLoading = true;
        const ticket = gpsGuard.ticket();
        const controller = new AbortController();
        routeController = controller;
        const timeout = setTimeout(() => controller.abort(), 10000);

        try {
            const response = await fetch(routeUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store',
                signal: controller.signal
            });

            if (!response.ok) {
                throw new Error('No se pudo consultar el seguimiento.');
            }

            const result = await response.json();

            // Ignorar respuestas GPS que llegaron después de finalizar el pedido.
            if (pageLeaving || currentStatus !== 'on_the_way' || document.hidden || !gpsGuard.current(ticket)) return;

            if (!gpsGuard.accept(result, ticket)) return;

            const driver = [
                result.driver.latitude,
                result.driver.longitude
            ];

            const destination = [
                result.destination.latitude,
                result.destination.longitude
            ];

            if (!deliveryMap) {
                deliveryMap = L.map(container);

                L.tileLayer(
                    'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
                    {
                        referrerPolicy: 'origin',
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a> contributors',
                        maxZoom: 19
                    }
                ).addTo(deliveryMap);
            }

            if (!driverMarker) {
                driverMarker = L.marker(driver)
                    .addTo(deliveryMap)
                    .bindPopup('Ubicación GPS del repartidor');
            } else {
                driverMarker.setLatLng(driver);
            }

            if (!destinationMarker) {
                destinationMarker = L.marker(destination)
                    .addTo(deliveryMap)
                    .bindPopup('Destino de entrega');
            } else {
                destinationMarker.setLatLng(destination);
            }

            const geometry = result.route?.geometry;

            if (
                result.available &&
                geometry?.type === 'LineString' &&
                Array.isArray(geometry.coordinates) &&
                geometry.coordinates.length >= 2 &&
                geometry.coordinates.every(point => Array.isArray(point) && point.length >= 2
                    && Number.isFinite(point[0]) && Number.isFinite(point[1])
                    && Math.abs(point[0]) <= 180 && Math.abs(point[1]) <= 90)
            ) {
                const routeKey = JSON.stringify(geometry.coordinates);

                if (routeKey !== lastRouteKey) {
                    originalRouteCoordinates = geometry.coordinates;
                    maximumProgressIndex = 0;
                    lastRouteKey = routeKey;
                }

                const points = originalRouteCoordinates;

                // Distancia aproximada en metros entre dos coordenadas.
                function distanceMeters(a, b) {
                    const lat1 = a[1] * Math.PI / 180;
                    const lat2 = b[1] * Math.PI / 180;
                    const dLat = lat2 - lat1;
                    const dLng = (b[0] - a[0]) * Math.PI / 180;

                    const h =
                        Math.sin(dLat / 2) ** 2 +
                        Math.cos(lat1) *
                        Math.cos(lat2) *
                        Math.sin(dLng / 2) ** 2;

                    return 6371000 * 2 * Math.asin(
                        Math.min(1, Math.sqrt(h))
                    );
                }

                const currentPosition = [
                    result.driver.longitude,
                    result.driver.latitude
                ];

                let nearestIndex = maximumProgressIndex;
                let nearestDistance = Infinity;

                // Buscar el punto más cercano, sin retroceder
                // a segmentos que ya se recorrieron.
                for (let i = maximumProgressIndex; i < points.length; i++) {
                    const distance = distanceMeters(
                        currentPosition,
                        points[i]
                    );

                    if (distance < nearestDistance) {
                        nearestDistance = distance;
                        nearestIndex = i;
                    }
                }

                // Solo avanzar si el GPS está cerca del recorrido.
                if (nearestDistance <= 80) {
                    maximumProgressIndex = Math.max(
                        maximumProgressIndex,
                        nearestIndex
                    );
                }

                const remainingPoints = points.slice(maximumProgressIndex);

                if (routeLayer) {
                    deliveryMap.removeLayer(routeLayer);
                    routeLayer = null;
                }

                if (remainingPoints.length >= 2) {
                    routeLayer = L.geoJSON({
                        type: 'LineString',
                        coordinates: remainingPoints
                    }, {
                        style: {
                            color: '#2563eb',
                            weight: 5,
                            opacity: 0.9
                        }
                    }).addTo(deliveryMap);
                }

                if (info) {
                    info.textContent =
                        nearestDistance <= 80
                            ? 'Repartidor en camino. El recorrido se actualiza con su GPS.'
                            : 'Ubicación GPS actualizada. El recorrido mostrado es referencial.';
                }
            } else {
                if (routeLayer) {
                    deliveryMap.removeLayer(routeLayer);
                    routeLayer = null;
                }

                originalRouteCoordinates = null;
                maximumProgressIndex = 0;
                lastRouteKey = null;

                if (info) {
                    info.textContent =
                        'GPS actualizado. Esperando el recorrido por calles.';
                }
            }

            if (!mapInitialized) {
                const bounds = L.latLngBounds([driver, destination]);

                deliveryMap.fitBounds(bounds, {
                    padding: [35, 35],
                    maxZoom: 16
                });

                mapInitialized = true;
            }

            setTimeout(() => deliveryMap.invalidateSize(), 100);

        } catch (error) {
            if (gpsGuard.current(ticket)) {
                gpsGuard.invalidate('No pudimos actualizar el mapa. Esperando una nueva ubicación; puedes actualizar el seguimiento.');
            }
        } finally {
            clearTimeout(timeout);
            if (routeController === controller) routeController = null;
            routeLoading = false;
        }
    }
    function updateTracking(status, paymentStatus) {
        const currentIndex = steps.indexOf(status);
        const cancelled = status === 'cancelled';

        const badge = document.getElementById('tracking-status');
        const cancelledBox = document.getElementById('tracking-cancelled');
        const paymentBox = document.getElementById('payment-status');
        const mapPlaceholder = document.getElementById('delivery-map-placeholder');

        if (badge) {
            badge.textContent = labels[status] ?? 'En seguimiento';

            badge.className = cancelled
                ? 'rounded-none bg-destructive/10 px-3 py-1 text-sm font-bold text-destructive'
                : 'rounded-none bg-green-100 px-3 py-1 text-sm font-bold text-green-800';
        }

        if (cancelledBox) {
            cancelledBox.hidden = !cancelled;
        }

        if (paymentBox) {
            paymentBox.textContent =
                paymentStatus === 'verified'
                    ? 'Verificado'
                    : paymentStatus === 'rejected'
                        ? 'Rechazado'
                        : 'Pendiente';
        }

        if (mapPlaceholder) {
            const showMap = status === 'on_the_way';

            mapPlaceholder.hidden = !showMap;

            if (showMap) {
                loadDeliveryMap();
            } else {
                suspendRoute();
            }
        }

        document.querySelectorAll('[data-tracking-step]').forEach(element => {
            const key = element.dataset.trackingStep;
            const index = steps.indexOf(key);

            const completed = !cancelled
                && currentIndex >= 0
                && index <= currentIndex;

            const active = !cancelled && index === currentIndex;

            const icon = element.querySelector('[data-step-icon]');
            const label = element.querySelector('[data-step-label]');

            if (icon) {
                icon.textContent = completed ? '✓' : '○';

                icon.className = completed
                    ? 'flex h-9 w-9 shrink-0 items-center justify-center rounded-none bg-green-100 font-bold text-green-700'
                    : 'flex h-9 w-9 shrink-0 items-center justify-center rounded-none bg-muted text-foreground/75';
            }

            if (label) {
                label.className = active
                    ? 'font-bold text-green-800'
                    : completed
                        ? 'font-medium text-foreground'
                        : 'text-foreground/75';
            }
        });
    }

    let currentStatus = @json($order->status);
    let currentPaymentStatus = @json($order->payment_status);

    updateTracking(currentStatus, currentPaymentStatus);

    @include('storefront.partials.confirmation-polling')
    const retry = document.getElementById('tracking-retry');
    retry.hidden = false;
    createConfirmationPolling({
        url: statusUrl, initialStatus: currentStatus,
        feedback: document.getElementById('tracking-sync-status'), retry,
        env: { document, window, navigator, fetch: window.fetch.bind(window), AbortController, setTimeout, clearTimeout },
        onUnavailable: suspendRoute,
        apply(data) {
            currentStatus = data.status;
            currentPaymentStatus = data.payment_status;
            updateTracking(currentStatus, currentPaymentStatus);
        },
    });
    });
</script>
@endsection
