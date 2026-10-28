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

<section class="mx-auto max-w-2xl px-4 py-10">

    <div class="rounded-3xl bg-white p-6 shadow-sm sm:p-10">

        <div class="mb-7 text-center">
            <h1 class="text-3xl font-black text-gray-900">
                Seguimiento de tu pedido
            </h1>

            <p class="mt-3 text-gray-600">
                Consulta el avance de tu pedido en tiempo real.
            </p>
        </div>

        <div class="rounded-2xl bg-gray-50 p-5">

            <p class="text-sm text-gray-500">
                Código de pedido
            </p>

            <p class="mt-1 break-all font-bold text-gray-900">
                {{ $order->code }}
            </p>

            <div class="mt-4 flex items-center justify-between gap-3">
                <span class="text-gray-600">Total</span>

                <strong class="text-xl text-red-800">
                    S/ {{ number_format((float) $order->total, 2) }}
                </strong>
            </div>

            <div class="mt-3 flex items-center justify-between gap-3">
                <span class="text-gray-600">Pago</span>

                <strong id="payment-status">
                    {{ $order->payment_status === 'verified'
                        ? 'Verificado'
                        : ($order->payment_status === 'rejected'
                            ? 'Rechazado'
                            : 'Pendiente') }}
                </strong>
            </div>
        </div>

        <div class="mt-6 rounded-2xl border border-gray-200 p-5">

            <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold">
                    Estado del pedido
                </h2>

                <span
                    id="tracking-status"
                    class="rounded-full bg-green-100 px-3 py-1 text-sm font-bold text-green-800"
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
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400"
                        >
                            ○
                        </span>

                        <span data-step-label class="text-gray-500">
                            {{ $label }}
                        </span>
                    </div>
                @endforeach
            </div>

            <div
                id="tracking-cancelled"
                class="mt-5 rounded-xl bg-red-50 p-4 text-sm font-medium text-red-700"
                @if($order->status !== 'cancelled') hidden @endif
            >
                Este pedido fue cancelado.
            </div>

            <p class="mt-6 text-xs text-gray-500">
                Los estados se consultan automáticamente cada 15 segundos.
            </p>
        </div>

        <div
            id="delivery-map-placeholder"
            class="mt-6 rounded-2xl border border-gray-200 bg-white p-5"
            hidden
        >
            <h2 class="text-lg font-bold text-gray-900">
                Tu pedido está en camino
            </h2>

            <p class="mt-2 text-sm text-gray-600">
                Tu pedido salió del restaurante.
                Sigue la ubicación del repartidor y su recorrido por calles.
            </p>

            <div
                id="delivery-route-map"
                class="mt-4 w-full overflow-hidden rounded-xl bg-gray-100"
                style="height: 320px;"
                aria-label="Mapa de seguimiento del repartidor"
            ></div>

            <p
                id="delivery-route-info"
                class="mt-3 text-sm text-gray-600"
                aria-live="polite"
            >
                Consultando ruta...
            </p>

            <p class="mt-2 text-xs text-gray-500">
                La ubicación depende de la señal GPS y la conexión del repartidor.
            </p>
        </div>

        <a
            href="{{ url('/') }}"
            class="mt-6 block rounded-xl border border-gray-300 px-5 py-3 text-center font-bold text-gray-800"
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

    async function loadDeliveryMap() {
        if (routeLoading || document.hidden || currentStatus !== 'on_the_way') return;

        const container = document.getElementById('delivery-route-map');
        const info = document.getElementById('delivery-route-info');

        if (!container || typeof window.L === 'undefined') {
            if (info) info.textContent = 'Mapa no disponible.';
            return;
        }

        routeLoading = true;

        try {
            const response = await fetch(routeUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
                cache: 'no-store'
            });

            if (!response.ok) {
                throw new Error('No se pudo consultar el seguimiento.');
            }

            const result = await response.json();

            // Ignorar respuestas GPS que llegaron después de finalizar el pedido.
            if (currentStatus !== 'on_the_way' || document.hidden) return;

            if (!result.gps_fresh || !result.driver) {
                if (routeLayer) {
                    deliveryMap.removeLayer(routeLayer);
                    routeLayer = null;
                }

                if (driverMarker) {
                    deliveryMap.removeLayer(driverMarker);
                    driverMarker = null;
                }

                lastRouteKey = null;

                if (info) {
                    info.textContent = result.gps_active
                        ? 'Esperando una ubicación GPS reciente del repartidor.'
                        : 'El repartidor todavía no ha iniciado el seguimiento GPS.';
                }

                return;
            }

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
                geometry.coordinates.length >= 2
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
            if (info && currentStatus === 'on_the_way') {
                info.textContent =
                    'No se pudo actualizar el mapa. Reintentando...';
            }
        } finally {
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
                ? 'rounded-full bg-red-100 px-3 py-1 text-sm font-bold text-red-700'
                : 'rounded-full bg-green-100 px-3 py-1 text-sm font-bold text-green-800';
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
                    ? 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 font-bold text-green-700'
                    : 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400';
            }

            if (label) {
                label.className = active
                    ? 'font-bold text-green-800'
                    : completed
                        ? 'font-medium text-gray-900'
                        : 'text-gray-500';
            }
        });
    }

    let currentStatus = @json($order->status);
    let currentPaymentStatus = @json($order->payment_status);

    updateTracking(currentStatus, currentPaymentStatus);

    let statusLoading = false;
    let trackingInterval = null;

    function isFinished() {
        return ['delivered', 'cancelled'].includes(currentStatus);
    }

    async function refreshTracking() {
        if (document.hidden || statusLoading || isFinished()) return;

        statusLoading = true;
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);

        try {
            const response = await fetch(statusUrl, {
                headers: { 'Accept': 'application/json' },
                cache: 'no-store',
                credentials: 'same-origin',
                signal: controller.signal
            });

            if (!response.ok) return;

            const data = await response.json();

            // Conservar el último estado válido ante respuestas inesperadas.
            if (
                !Object.prototype.hasOwnProperty.call(labels, data.status)
                || !['pending', 'verified', 'rejected'].includes(data.payment_status)
            ) return;

            currentStatus = data.status;
            currentPaymentStatus = data.payment_status;

            updateTracking(currentStatus, currentPaymentStatus);

            if (isFinished()) {
                clearInterval(trackingInterval);
                trackingInterval = null;
            }
        } catch (error) {
            console.warn('No se pudo actualizar el seguimiento.');
        } finally {
            clearTimeout(timeout);
            statusLoading = false;
        }
    }

    // Una sola consulta de estado por ciclo; updateTracking actualiza el mapa
    // únicamente mientras el pedido está en camino.
    if (!isFinished()) {
        trackingInterval = setInterval(refreshTracking, 15000);
        refreshTracking();
        document.addEventListener('visibilitychange', refreshTracking);
    }
    });
</script>
@endsection
