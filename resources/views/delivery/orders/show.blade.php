<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Entrega {{ $order->code }} | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 text-slate-900">
<main class="max-w-2xl mx-auto p-4 space-y-5">

    <a href="{{ route('delivery.orders.index') }}"
       class="inline-block text-blue-700 font-semibold">
        ← Mis entregas
    </a>

    <section class="bg-white rounded-2xl p-5 shadow-sm space-y-4">
        <div>
            <h1 class="text-2xl font-bold">
                Entrega {{ $order->code }}
            </h1>

            <p class="text-sm text-slate-500">
                Estado: {{ $order->status }}
            </p>
        </div>

        <div class="border-t pt-4 space-y-2">
            <p><strong>Cliente:</strong> {{ $order->customer_name }}</p>
            <p><strong>Dirección:</strong> {{ $order->delivery_address }}</p>
        </div>

        @if($order->delivery_latitude !== null && $order->delivery_longitude !== null)
            <a
                href="https://www.google.com/maps/dir/?api=1&destination={{ urlencode($order->delivery_latitude.','.$order->delivery_longitude) }}"
                target="_blank"
                rel="noopener noreferrer"
                class="block text-center rounded-xl bg-blue-600 text-white font-semibold py-3"
            >
                Abrir destino en Google Maps
            </a>
        @endif
    </section>

    <section class="bg-white rounded-2xl p-5 shadow-sm space-y-4">
        <h2 class="text-xl font-bold">Seguimiento GPS</h2>

        <p class="text-sm text-slate-600">
            Cuando el pedido esté en camino, podrás activar el GPS para
            compartir tu ubicación con el cliente.
        </p>

        <div id="gps-status"
             role="status"
             aria-live="polite"
             class="rounded-xl bg-slate-100 p-4 text-sm">
            GPS desactivado.
        </div>

        @if($order->status === 'on_the_way')
            <div class="space-y-3">
                <button
                    id="gps-start"
                    type="button"
                    class="w-full rounded-xl bg-green-600 text-white py-3 font-semibold"
                >
                    Iniciar seguimiento GPS
                </button>

                <button
                    id="gps-stop"
                    type="button"
                    disabled
                    class="w-full rounded-xl bg-red-600 text-white py-3 font-semibold disabled:opacity-50"
                >
                    Detener seguimiento
                </button>
            </div>
        @else
            <p class="rounded-xl bg-amber-50 p-4 text-amber-800 text-sm">
                El seguimiento GPS estará disponible cuando el pedido esté en camino.
            </p>
        @endif
    </section>

</main>
@if($order->status === 'on_the_way')
<script>
(() => {
    const startButton = document.getElementById('gps-start');
    const stopButton = document.getElementById('gps-stop');
    const status = document.getElementById('gps-status');

    const csrf = @json(csrf_token());

    const urls = {
        start: @json(route('delivery.gps.start', $order)),
        update: @json(route('delivery.gps.update', $order)),
        stop: @json(route('delivery.gps.stop', $order)),
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
                'X-CSRF-TOKEN': csrf,
            },
            body: JSON.stringify(data),
        });

        const result = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(result.message || 'Error de comunicación.');
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
            showStatus('Seguimiento GPS detenido.');
        } catch (error) {
            showStatus('No se pudo confirmar la detención: ' + error.message);
        } finally {
            resetButtons();
        }
    }

    async function sendPosition(position) {
        if (!active || sending) return;

        const now = Date.now();

        if (now - lastSentAt < 5000) return;

        const { latitude, longitude, accuracy } = position.coords;

        if (accuracy > 100) {
            showStatus('Esperando una ubicación GPS más precisa...');
            return;
        }

        sending = true;

        try {
            await post(urls.update, {
                latitude,
                longitude,
                accuracy,
            });

            lastSentAt = Date.now();
            showStatus(
                'GPS activo. Ubicación compartida a las '
                + new Date().toLocaleTimeString('es-PE')
            );
        } catch (error) {
            showStatus('Error GPS: ' + error.message);
        } finally {
            sending = false;
        }
    }

    startButton.addEventListener('click', async () => {
        if (!navigator.geolocation) {
            showStatus('Este dispositivo no admite geolocalización.');
            return;
        }

        if (!window.isSecureContext) {
            showStatus('El GPS requiere HTTPS.');
            return;
        }

        startButton.disabled = true;
        showStatus('Solicitando permiso para utilizar el GPS...');

        try {
            await post(urls.start);

            active = true;
            stopButton.disabled = false;

            watchId = navigator.geolocation.watchPosition(
                sendPosition,
                error => {
                    showStatus('No se pudo obtener el GPS: ' + error.message);

                    if (error.code === 1) {
                        stopTracking();
                    }
                },
                {
                    enableHighAccuracy: true,
                    maximumAge: 3000,
                    timeout: 15000,
                }
            );
        } catch (error) {
            resetButtons();
            showStatus(error.message);
        }
    });

    stopButton.addEventListener('click', stopTracking);
})();
</script>
@endif
</body>
</html>
