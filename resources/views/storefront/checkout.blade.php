@extends('layouts.storefront')

@section('title', 'Confirmar pedido | Chifa Mi Hogar')

@section('content')

<section class="mx-auto max-w-3xl px-4 py-10">

    <h1 class="mb-6 text-3xl font-black">
        Confirmar pedido
    </h1>

    @if($errors->any())
        <div class="mb-5 rounded-xl bg-red-50 p-4 text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if(session('checkout_quote'))
        @php($quote = session('checkout_quote'))

        <div class="mb-6 rounded-2xl bg-green-50 p-5">
            <h2 class="font-bold">Cotización actualizada</h2>

            <p>Productos: S/ {{ $quote['subtotal'] }}</p>
            <p>Delivery: S/ {{ $quote['delivery_fee'] }}</p>

            <p class="mt-3 text-xl font-black">
                Total: S/ {{ $quote['total'] }}
            </p>

            <p class="mt-2 text-sm">
                Los precios se recalcularán al confirmar.
            </p>
        </div>
    @endif

    <form id="checkout-form" method="POST"
          action="{{ route('cart.checkout.quote') }}"
          class="space-y-5 rounded-2xl bg-white p-6 shadow-sm">

        @csrf
        <input
            type="hidden"
            name="checkout_token"
            value="{{ session('checkout_token') }}"
        >
        <h2 class="text-xl font-bold">Datos del cliente</h2>

        <input name="customer_name"
               value="{{ old('customer_name') }}"
               placeholder="Nombre completo"
               maxlength="150"
               class="w-full rounded-xl border p-3">

        <input name="customer_phone"
               value="{{ old('customer_phone') }}"
               placeholder="Celular de 9 dígitos"
               inputmode="numeric"
               maxlength="9"
               class="w-full rounded-xl border p-3">

        <input name="customer_email"
               type="email"
               value="{{ old('customer_email') }}"
               placeholder="Correo electrónico (opcional)"
               class="w-full rounded-xl border p-3">

        <h2 class="text-xl font-bold">Entrega</h2>

        <select name="delivery_type" id="delivery_type"
                class="w-full rounded-xl border p-3">
            <option value="pickup" @selected(old('delivery_type') === 'pickup')>
                Recojo en tienda
            </option>
            <option value="delivery" @selected(old('delivery_type') === 'delivery')>
                Delivery
            </option>
        </select>

        <div id="delivery_fields" class="space-y-3">
            <input name="delivery_address"
                   value="{{ old('delivery_address') }}"
                   placeholder="Dirección de entrega"
                   maxlength="500"
                   class="w-full rounded-xl border p-3">

            <input name="delivery_reference"
                   value="{{ old('delivery_reference') }}"
                   placeholder="Referencia"
                   maxlength="500"
                   class="w-full rounded-xl border p-3">

                <div class="space-y-4 rounded-2xl border p-4">

                    <h3 class="text-lg font-bold">
                        Ubicación de entrega
                    </h3>

                    <p class="text-sm text-gray-600">
                        Selecciona el lugar exacto donde deseas recibir tu pedido.
                    </p>

                    <div class="grid gap-3 sm:grid-cols-2">

                        <button type="button"
                                id="btn-current-location"
                                class="rounded-xl bg-[#982E26] px-4 py-3 font-bold text-white">
                            📍 Usar mi ubicación actual
                        </button>

                        <button type="button"
                                id="btn-select-map"
                                class="rounded-xl border border-[#982E26] px-4 py-3 font-bold text-[#982E26]">
                            🗺️ Elegir otra ubicación
                        </button>

                    </div>

                    <div id="delivery-map"
                        class="h-80 w-full rounded-xl border"
                        style="z-index:0">
                    </div>

                    <input type="hidden"
                        name="delivery_latitude"
                        id="delivery_latitude"
                        value="{{ old('delivery_latitude') }}">

                    <input type="hidden"
                        name="delivery_longitude"
                        id="delivery_longitude"
                        value="{{ old('delivery_longitude') }}">

                    <div id="location-status"
                        role="status"
                        class="rounded-xl bg-gray-50 p-3 text-sm text-gray-600">
                        Aún no has confirmado una ubicación.
                    </div>

                    <button type="button"
                            id="btn-confirm-location"
                            class="w-full rounded-xl bg-green-700 px-4 py-3 font-bold text-white">
                        Confirmar esta ubicación
                    </button>

                </div>
        </div>

        <h2 class="text-xl font-bold">Método de pago</h2>

        <select name="payment_method" id="payment_method"
                class="w-full rounded-xl border p-3">
            <option value="yape" @selected(old('payment_method') === 'yape')>Yape</option>
            <option value="plin" @selected(old('payment_method') === 'plin')>Plin</option>
            <option value="cash" @selected(old('payment_method') === 'cash')>Efectivo</option>
        </select>

        <div id="cash_fields" class="space-y-3">
            <select name="cash_payment_type" id="cash_payment_type"
                    class="w-full rounded-xl border p-3">
                <option value="exact">Pago exacto</option>
                <option value="change">Necesito vuelto</option>
            </select>

            <input name="cash_received_amount"
                   id="cash_received_amount"
                   type="number" min="0" step="0.01"
                   value="{{ old('cash_received_amount') }}"
                   placeholder="¿Con cuánto pagarás?"
                   class="w-full rounded-xl border p-3">
        </div>

        <textarea name="customer_notes"
                  maxlength="1000"
                  placeholder="Observaciones del pedido"
                  class="w-full rounded-xl border p-3">{{ old('customer_notes') }}</textarea>

        <div class="grid gap-3 sm:grid-cols-2">

            <button type="submit"
                    class="rounded-xl border border-[#982E26] px-5 py-3 font-bold text-[#982E26]">
                Calcular cotización
            </button>

            <button type="submit"
                    formaction="{{ route('cart.checkout.confirm') }}"
                    class="rounded-xl bg-[#982E26] px-5 py-3 font-bold text-white">
                Confirmar pedido
            </button>

        </div>

        <p class="text-xs text-gray-500">
            Confirmar pedido registra la solicitud. Los pagos con Yape y Plin
            quedan pendientes de verificación.
        </p>

    </form>

</section>

<script>
    const deliveryType = document.getElementById('delivery_type');
    const deliveryFields = document.getElementById('delivery_fields');
    const paymentMethod = document.getElementById('payment_method');
    const cashFields = document.getElementById('cash_fields');
    const cashType = document.getElementById('cash_payment_type');
    const cashAmount = document.getElementById('cash_received_amount');

    function updateFields() {
        deliveryFields.classList.toggle(
            'hidden',
            deliveryType.value !== 'delivery'
        );

        cashFields.classList.toggle(
            'hidden',
            paymentMethod.value !== 'cash'
        );

        cashAmount.classList.toggle(
            'hidden',
            paymentMethod.value !== 'cash' ||
            cashType.value !== 'change'
        );
    }

    deliveryType.addEventListener('change', updateFields);
    paymentMethod.addEventListener('change', updateFields);
    cashType.addEventListener('change', updateFields);

    updateFields();
</script>
<script>
document.addEventListener('DOMContentLoaded', function () {

    const mapElement = document.getElementById('delivery-map');
    const latInput = document.getElementById('delivery_latitude');
    const lngInput = document.getElementById('delivery_longitude');
    const status = document.getElementById('location-status');

    const currentButton = document.getElementById('btn-current-location');
    const selectButton = document.getElementById('btn-select-map');
    const confirmButton = document.getElementById('btn-confirm-location');
    const deliveryType = document.getElementById('delivery_type');

    // Centro inicial ilustrativo: Lima.
    // No representa la ubicación del cliente.
    const initialLat = -12.0464;
    const initialLng = -77.0428;

    const map = L.map(mapElement).setView(
        [initialLat, initialLng],
        12
    );

    L.tileLayer(
        'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
        {
            maxZoom: 19,
            attribution: '&copy; OpenStreetMap contributors'
        }
    ).addTo(map);

    let marker = null;
    let selectedPoint = null;
    let confirmed = false;

    function clearConfirmation() {
        confirmed = false;
        latInput.value = '';
        lngInput.value = '';

        status.textContent =
            'Ubicación seleccionada. Presiona "Confirmar esta ubicación".';
    }

    function setPoint(lat, lng, zoom = 17) {

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        selectedPoint = { lat, lng };

        if (marker) {
            marker.setLatLng([lat, lng]);
        } else {
            marker = L.marker([lat, lng], {
                draggable: true
            }).addTo(map);

            marker.on('dragend', function () {
                const position = marker.getLatLng();

                selectedPoint = {
                    lat: position.lat,
                    lng: position.lng
                };

                clearConfirmation();
            });
        }

        map.setView([lat, lng], zoom);
        clearConfirmation();
    }

    map.on('click', function (event) {
        setPoint(event.latlng.lat, event.latlng.lng, map.getZoom());
    });

    currentButton.addEventListener('click', function () {

        if (!navigator.geolocation) {
            status.textContent =
                'Tu navegador no admite geolocalización. Selecciona el punto en el mapa.';
            return;
        }

        status.textContent = 'Buscando tu ubicación...';

        navigator.geolocation.getCurrentPosition(
            function (position) {
                setPoint(
                    position.coords.latitude,
                    position.coords.longitude
                );

                status.textContent =
                    'Ubicación detectada. Verifica el marcador y confirma.';
            },
            function () {
                status.textContent =
                    'No fue posible obtener tu ubicación. Puedes elegirla en el mapa.';
            },
            {
                enableHighAccuracy: true,
                timeout: 15000,
                maximumAge: 0
            }
        );
    });

    selectButton.addEventListener('click', function () {
        mapElement.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

        map.invalidateSize();

        status.textContent =
            'Haz clic en el lugar de entrega o arrastra el marcador.';
    });

    confirmButton.addEventListener('click', function () {

        if (!selectedPoint) {
            status.textContent =
                'Primero selecciona un punto en el mapa.';
            return;
        }

        latInput.value = selectedPoint.lat.toFixed(7);
        lngInput.value = selectedPoint.lng.toFixed(7);

        confirmed = true;

        status.textContent =
            'Ubicación confirmada correctamente.';
    });

    // Restaurar ubicación si Laravel devolvió errores de validación.
    const oldLat = Number(latInput.value);
    const oldLng = Number(lngInput.value);

    if (
        latInput.value !== '' &&
        lngInput.value !== '' &&
        Number.isFinite(oldLat) &&
        Number.isFinite(oldLng) &&
        oldLat >= -90 && oldLat <= 90 &&
        oldLng >= -180 && oldLng <= 180
    ) {
        setPoint(oldLat, oldLng);
        latInput.value = oldLat.toFixed(7);
        lngInput.value = oldLng.toFixed(7);
        confirmed = true;
        status.textContent = 'Ubicación restaurada y confirmada.';
    }

    // Leaflet necesita recalcular dimensiones al mostrar el mapa.
    deliveryType.addEventListener('change', function () {
        if (deliveryType.value === 'delivery') {
            setTimeout(() => map.invalidateSize(), 100);
        }
    });

    setTimeout(() => map.invalidateSize(), 100);

    // Impedir cotizar o confirmar delivery sin ubicación validada.
    document.getElementById('checkout-form')
        .addEventListener('submit', function (event) {

            if (deliveryType.value === 'delivery' && !confirmed) {
                event.preventDefault();

                status.textContent =
                    'Debes seleccionar y confirmar la ubicación de entrega.';

                mapElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });

});
</script>
@endsection
