
@extends('layouts.storefront')

@section('title', 'Confirmar pedido | Chifa Mi Hogar')

@php
    // Configuración real guardada por el administrador.
    $settings = \App\Models\StoreSetting::current()
        ->load('paymentQr');

    $yapeNumber = preg_replace(
        '/\D+/',
        '',
        (string) $settings->yape_number
    );

    $paymentHolder = trim(
        (string) $settings->payment_holder
    );

    $qrUrl = $settings->paymentQr?->detailUrl();

    $yapeAvailable = filled($yapeNumber) || filled($qrUrl);

    $selectedPayment = old('payment_method', 'cash');

    if (!in_array($selectedPayment, ['cash', 'yape'], true)) {
        $selectedPayment = 'cash';
    }

    if (!$yapeAvailable) {
        $selectedPayment = 'cash';
    }

    $selectedCashType = old('cash_payment_type', 'exact');

    $quote = session('checkout_quote');
@endphp

@section('content')

<section class="mx-auto max-w-6xl px-4 pb-16 pt-8 sm:px-6 lg:pt-12">

    {{-- ENCABEZADO --}}

    <div class="mb-8">
        <a
            href="{{ route('cart.index') }}"
            class="text-sm font-semibold text-[#982E26] hover:underline"
        >
            ← Volver al carrito
        </a>

        <p class="mt-7 text-xs font-bold uppercase tracking-[0.16em] text-[#982E26]">
            Último paso
        </p>

        <h1 class="mt-2 text-3xl font-black tracking-tight text-gray-950 sm:text-4xl">
            Confirmar pedido
        </h1>

        <p class="mt-2 text-sm leading-6 text-gray-500">
            Completa tus datos, confirma tu ubicación y elige cómo deseas pagar.
        </p>
    </div>

    {{-- ERRORES --}}

    @if($errors->any())
        <div
            role="alert"
            class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
            <p class="font-bold">Revisa los siguientes datos:</p>

            <ul class="mt-2 list-inside list-disc space-y-1">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- COTIZACIÓN DESDE LARAVEL --}}

    @if($quote)
        <div
            class="mb-7 rounded-2xl border border-[#E6DDD1] bg-white p-5 shadow-sm sm:p-6"
        >
            <div class="flex items-center gap-3">
                <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-green-50 text-green-700">
                    ✓
                </div>

                <div>
                    <h2 class="font-black text-gray-950">
                        Cotización calculada
                    </h2>

                    <p class="text-xs text-gray-500">
                        Importes calculados desde nuestro sistema.
                    </p>
                </div>
            </div>

            <div class="mt-5 space-y-3 text-sm">
                <div class="flex justify-between gap-3">
                    <span class="text-gray-500">Productos</span>
                    <span class="font-semibold">
                        S/ {{ number_format((float) $quote['subtotal'], 2) }}
                    </span>
                </div>

                <div class="flex justify-between gap-3">
                    <span class="text-gray-500">Delivery</span>
                    <span class="font-semibold">
                        S/ {{ number_format((float) $quote['delivery_fee'], 2) }}
                    </span>
                </div>

                <div class="flex justify-between gap-3 border-t border-gray-100 pt-4">
                    <span class="font-black">Total cotizado</span>

                    <strong class="text-2xl text-[#982E26]">
                        S/ {{ number_format((float) $quote['total'], 2) }}
                    </strong>
                </div>
            </div>

            <p class="mt-3 text-xs leading-5 text-gray-500">
                Si cambias tu ubicación o tus datos de pago,
                vuelve a calcular. El total definitivo
                se verificará al confirmar el pedido.
            </p>
        </div>
    @endif

    <form
        id="checkout-form"
        method="POST"
        action="{{ route('cart.checkout.quote') }}"
        class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_300px]"
    >
        @csrf
            @include('legal.collection-notice')

        <input
            type="hidden"
            name="checkout_token"
            value="{{ session('checkout_token') }}"
        >

        {{-- Solo delivery --}}
        <input
            type="hidden"
            name="delivery_type"
            value="delivery"
        >

        <div class="min-w-0 space-y-6">

            {{-- =============================================
                 1. DATOS DEL CLIENTE
            ============================================== --}}

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">

                <div class="mb-6 flex items-center gap-3">
                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#982E26] text-sm font-black text-white">
                        1
                    </span>

                    <div>
                        <h2 class="text-lg font-black text-gray-950">
                            Tus datos
                        </h2>

                        <p class="text-xs text-gray-500">
                            Información para identificar tu pedido.
                        </p>
                    </div>
                </div>

                <div class="space-y-4">

                    <div>
                        <label
                            for="customer_name"
                            class="mb-2 block text-sm font-semibold text-gray-800"
                        >
                            Nombre completo
                        </label>

                        <input
                            id="customer_name"
                            name="customer_name"
                            type="text"
                            autocomplete="name"
                            maxlength="150"
                            value="{{ old('customer_name') }}"
                            placeholder="Ingresa tu nombre completo"
                            class="w-full rounded-xl border border-gray-200 bg-[#FDFCFB] px-4 py-3 text-sm outline-none transition focus:border-[#982E26] focus:ring-2 focus:ring-red-100"
                        >
                    </div>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <div>
                            <label
                                for="customer_phone"
                                class="mb-2 block text-sm font-semibold text-gray-800"
                            >
                                Número de celular
                            </label>

                            <input
                                id="customer_phone"
                                name="customer_phone"
                                type="tel"
                                inputmode="numeric"
                                autocomplete="tel-national"
                                pattern="9[0-9]{8}"
                                maxlength="9"
                                value="{{ old('customer_phone') }}"
                                placeholder="9XXXXXXXX"
                                class="w-full rounded-xl border border-gray-200 bg-[#FDFCFB] px-4 py-3 text-sm outline-none transition focus:border-[#982E26] focus:ring-2 focus:ring-red-100"
                            >
                        </div>

                        <div>
                            <label
                                for="customer_email"
                                class="mb-2 block text-sm font-semibold text-gray-800"
                            >
                                Correo electrónico
                                <span class="font-normal text-gray-400">
                                    (opcional)
                                </span>
                            </label>

                            <input
                                id="customer_email"
                                name="customer_email"
                                type="email"
                                autocomplete="email"
                                maxlength="150"
                                value="{{ old('customer_email') }}"
                                placeholder="correo@ejemplo.com"
                                class="w-full rounded-xl border border-gray-200 bg-[#FDFCFB] px-4 py-3 text-sm outline-none transition focus:border-[#982E26] focus:ring-2 focus:ring-red-100"
                            >
                        </div>
                    </div>

                </div>
            </section>

            {{-- =============================================
                 2. ENTREGA
            ============================================== --}}

            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">

                <div class="mb-5 flex items-center gap-3">

                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#982E26] text-sm font-black text-white">
                        2
                    </span>

                    <div>
                        <h2 class="text-lg font-black text-gray-950">
                            Dirección de entrega
                        </h2>

                        <p class="text-xs text-gray-500">
                            Entregamos tus platos a domicilio.
                        </p>
                    </div>

                </div>

                <div class="space-y-4">

                    <div>
                        <label
                            for="delivery_address"
                            class="mb-2 block text-sm font-semibold text-gray-800"
                        >
                            Dirección completa
                        </label>

                        <input
                            id="delivery_address"
                            name="delivery_address"
                            type="text"
                            autocomplete="street-address"
                            maxlength="500"
                            value="{{ old('delivery_address') }}"
                            placeholder="Avenida, calle, número y distrito"
                            class="w-full rounded-xl border border-gray-200 bg-[#FDFCFB] px-4 py-3 text-sm outline-none transition focus:border-[#982E26] focus:ring-2 focus:ring-red-100"
                        >
                    </div>

                    <div>
                        <label
                            for="delivery_reference"
                            class="mb-2 block text-sm font-semibold text-gray-800"
                        >
                            Referencia
                            <span class="font-normal text-gray-400">
                                (opcional)
                            </span>
                        </label>

                        <input
                            id="delivery_reference"
                            name="delivery_reference"
                            type="text"
                            maxlength="500"
                            value="{{ old('delivery_reference') }}"
                            placeholder="Color de puerta, piso, lugar cercano..."
                            class="w-full rounded-xl border border-gray-200 bg-[#FDFCFB] px-4 py-3 text-sm outline-none transition focus:border-[#982E26] focus:ring-2 focus:ring-red-100"
                        >
                    </div>

                    {{-- MAPA ACTUAL --}}
                    <div class="rounded-2xl border border-gray-200 p-3 sm:p-4">

                        <div class="mb-4">
                            <h3 class="text-sm font-black text-gray-950">
                                Confirma tu ubicación en el mapa
                            </h3>

                            <p class="mt-1 text-xs leading-5 text-gray-500">
                                Usa tu ubicación actual o mueve el marcador hasta
                                el lugar exacto de entrega.
                            </p>
                        </div>

                        <div class="grid gap-2 sm:grid-cols-2">

                            <button
                                type="button"
                                id="btn-current-location"
                                class="min-h-11 rounded-xl bg-[#982E26] px-3 py-3 text-xs font-bold text-white transition hover:bg-[#B5382E] sm:text-sm"
                            >
                                📍 Mi ubicación actual
                            </button>

                            <button
                                type="button"
                                id="btn-select-map"
                                class="min-h-11 rounded-xl border border-[#E8D2CD] bg-white px-3 py-3 text-xs font-bold text-[#982E26] transition hover:bg-[#FFF6F3] sm:text-sm"
                            >
                                Elegir en el mapa
                            </button>

                        </div>

                        <div
                            id="delivery-map"
                            class="relative mt-4 w-full overflow-hidden rounded-xl border border-gray-200 bg-gray-100"
                            style="height: 320px; min-height: 320px; width: 100%; z-index: 0;"
                            aria-label="Mapa para seleccionar la ubicación de entrega"
                        ></div>

                        <input
                            type="hidden"
                            name="delivery_latitude"
                            id="delivery_latitude"
                            value="{{ old('delivery_latitude') }}"
                        >

                        <input
                            type="hidden"
                            name="delivery_longitude"
                            id="delivery_longitude"
                            value="{{ old('delivery_longitude') }}"
                        >

                        <div
                            id="location-status"
                            role="status"
                            aria-live="polite"
                            class="mt-3 rounded-xl bg-gray-50 px-4 py-3 text-xs leading-5 text-gray-600"
                        >
                            Selecciona y confirma el punto de entrega.
                        </div>

                        <button
                            type="button"
                            id="btn-confirm-location"
                            class="mt-3 min-h-11 w-full rounded-xl border border-green-700 bg-white px-4 py-3 text-sm font-bold text-green-800 transition hover:bg-green-50"
                        >
                            ✓ Confirmar esta ubicación
                        </button>

                    </div>

                </div>

            </section>

            {{-- =============================================
                 3. MÉTODO DE PAGO
            ============================================== --}}

            <section
                id="payment-section"
                class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6"
            >

                <div class="mb-6 flex items-center gap-3">

                    <span class="flex h-9 w-9 items-center justify-center rounded-full bg-[#982E26] text-sm font-black text-white">
                        3
                    </span>

                    <div>
                        <h2 class="text-lg font-black text-gray-950">
                            Método de pago
                        </h2>

                        <p class="text-xs text-gray-500">
                            Selecciona una de las dos opciones.
                        </p>
                    </div>

                </div>

                {{-- DOS OPCIONES, SIN SELECT --}}

                <div class="grid grid-cols-2 gap-3">

                    {{-- EFECTIVO --}}
                    <label
                        data-payment-card="cash"
                        class="relative flex min-h-28 cursor-pointer flex-col items-center justify-center gap-2 rounded-2xl border-2 p-4 text-center transition"
                    >
                        <input
                            type="radio"
                            name="payment_method"
                            value="cash"
                            class="sr-only"
                            @checked($selectedPayment === 'cash')
                        >

                        <span class="text-3xl" aria-hidden="true">💵</span>

                        <span class="text-sm font-black">
                            Efectivo
                        </span>

                        <span class="text-[11px] text-gray-500">
                            Paga al recibir
                        </span>
                    </label>

                    {{-- YAPE --}}
                    <label
                        data-payment-card="yape"
                        class="relative flex min-h-28 flex-col items-center justify-center gap-2 rounded-2xl border-2 p-4 text-center transition {{ !$yapeAvailable ? 'cursor-not-allowed opacity-50' : 'cursor-pointer' }}"
                    >
                        <input
                            type="radio"
                            name="payment_method"
                            value="yape"
                            class="sr-only"
                            @checked($selectedPayment === 'yape')
                            @disabled(!$yapeAvailable)
                        >

                        <span
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-purple-700 text-lg font-black text-white"
                            aria-hidden="true"
                        >
                            Y
                        </span>

                        <span class="text-sm font-black">
                            Yape
                        </span>

                        <span class="text-[11px] text-gray-500">
                            Transferencia digital
                        </span>
                    </label>

                </div>

                {{-- PAGO EN EFECTIVO --}}
                <div
                    id="cash-panel"
                    class="mt-5 rounded-2xl border border-gray-100 bg-[#FAF8F5] p-4 sm:p-5"
                >
                    <div class="mb-4">
                        <h3 class="text-sm font-black text-gray-950">
                            Pago en efectivo
                        </h3>

                        <p class="mt-1 text-xs leading-5 text-gray-500">
                            Paga al momento de recibir tu pedido.
                        </p>
                    </div>

                    <div>
                        <label
                            for="cash_payment_type"
                            class="mb-2 block text-xs font-semibold text-gray-700"
                        >
                            ¿Necesitas vuelto?
                        </label>

                        <select
                            id="cash_payment_type"
                            name="cash_payment_type"
                            class="w-full rounded-xl border border-gray-200 bg-white px-4 py-3 text-sm focus:border-[#982E26] focus:outline-none"
                        >
                            <option
                                value="exact"
                                @selected($selectedCashType === 'exact')
                            >
                                Pagaré el monto exacto
                            </option>

                            <option
                                value="change"
                                @selected($selectedCashType === 'change')
                            >
                                Necesito vuelto
                            </option>
                        </select>
                    </div>

                    <div id="cash-change-panel" class="mt-4 hidden">

                        <label
                            for="cash_received_amount"
                            class="mb-2 block text-xs font-semibold text-gray-700"
                        >
                            ¿Con cuánto dinero pagarás?
                        </label>

                        <div class="relative">

                            <span class="absolute left-4 top-3 text-sm font-bold text-gray-500">
                                S/
                            </span>

                            <input
                                id="cash_received_amount"
                                name="cash_received_amount"
                                type="number"
                                inputmode="decimal"
                                min="0"
                                step="0.01"
                                value="{{ old('cash_received_amount') }}"
                                placeholder="50.00"
                                class="w-full rounded-xl border border-gray-200 bg-white py-3 pl-11 pr-4 text-sm focus:border-[#982E26] focus:outline-none"
                            >

                        </div>

                        <p class="mt-2 text-xs leading-5 text-gray-500">
                            Indica un importe superior al total de tu pedido
                            para que podamos preparar el vuelto.
                        </p>

                    </div>
                </div>

                {{-- PAGO CON YAPE --}}
                <div
                    id="yape-panel"
                    class="mt-5 hidden rounded-2xl border border-purple-100 bg-[#FCFAFF] p-4 sm:p-5"
                >

                    <div class="mb-5">

                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-purple-700 font-black text-white">
                                Y
                            </span>

                            <h3 class="text-sm font-black text-gray-950">
                                Paga con Yape
                            </h3>
                        </div>

                        <p class="mt-3 text-xs leading-6 text-gray-600">
                            Puedes escanear nuestro QR con otro dispositivo
                            o copiar el número para yapear desde tu celular.
                        </p>

                    </div>

                    {{-- QR CONFIGURADO DESDE ADMIN --}}
                    @if($qrUrl)

                        <div class="mb-5 text-center">

                            <button
                                id="open-qr"
                                type="button"
                                class="inline-flex flex-col items-center rounded-2xl border border-purple-100 bg-white p-3 shadow-sm transition hover:border-purple-300 hover:shadow-md"
                                aria-label="Ampliar código QR de Yape"
                            >
                                <img
                                    src="{{ $qrUrl }}"
                                    alt="Código QR oficial de Yape de Chifa Mi Hogar"
                                    class="h-44 w-44 object-contain sm:h-48 sm:w-48"
                                    loading="lazy"
                                >

                                <span class="mt-2 text-xs font-bold text-purple-800">
                                    Toca el QR para ampliarlo
                                </span>
                            </button>

                            <p class="mt-2 text-[11px] text-gray-500">
                                Puedes ampliar el QR y tomar una captura de pantalla.
                            </p>

                        </div>

                    @endif

                    {{-- NÚMERO PARA COPIAR --}}
                    @if($yapeNumber)

                        <div class="rounded-xl border border-purple-100 bg-white p-4">

                            <p class="text-xs font-semibold text-gray-500">
                                Número de Yape
                            </p>

                            <div class="mt-2 flex flex-wrap items-center justify-between gap-3">

                                <strong
                                    id="yape-number"
                                    class="text-xl font-black tracking-wide text-gray-950"
                                >
                                    {{ $yapeNumber }}
                                </strong>

                                <button
                                    id="copy-yape-number"
                                    type="button"
                                    class="inline-flex min-h-10 items-center justify-center rounded-lg bg-purple-700 px-4 py-2 text-xs font-bold text-white transition hover:bg-purple-800"
                                >
                                    Copiar número
                                </button>

                            </div>

                            <p
                                id="copy-feedback"
                                role="status"
                                aria-live="polite"
                                class="mt-2 text-xs text-gray-500"
                            >
                                Copia el número y pégalo en Yape.
                            </p>

                        </div>

                    @endif

                    {{-- TITULAR --}}
                    <div class="mt-4 rounded-xl border border-purple-100 bg-white p-4">

                        <p class="text-xs font-semibold text-gray-500">
                            Nombre del titular
                        </p>

                        <p class="mt-1 text-base font-black text-gray-950">
                            {{ $paymentHolder !== ''
                                ? $paymentHolder
                                : 'Titular no configurado' }}
                        </p>

                        <p class="mt-2 text-xs leading-5 text-gray-500">
                            Comprueba que el nombre mostrado en tu aplicación
                            coincida antes de transferir.
                        </p>

                    </div>

                    <div class="mt-4 rounded-xl bg-purple-50 p-4">

                        <p class="text-xs font-bold text-purple-900">
                            Importante
                        </p>

                        <p class="mt-1 text-xs leading-6 text-purple-900">
                            Confirma el importe final antes de pagar.
                            Después de registrar tu pedido, podrás enviar
                            el comprobante por WhatsApp. Tu pago permanecerá
                            pendiente hasta que el negocio lo verifique.
                        </p>

                    </div>

                </div>

                @unless($yapeAvailable)
                    <p class="mt-4 rounded-xl bg-amber-50 p-3 text-xs text-amber-800">
                        Yape no está configurado actualmente.
                        Puedes seleccionar pago en efectivo.
                    </p>
                @endunless

            </section>

            {{-- OBSERVACIONES --}}
            <section class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm sm:p-6">

                <label
                    for="customer_notes"
                    class="block text-sm font-black text-gray-950"
                >
                    Indicaciones adicionales
                    <span class="font-normal text-gray-400">
                        (opcional)
                    </span>
                </label>

                <p class="mt-1 text-xs text-gray-500">
                    Puedes añadir una indicación para tu pedido.
                </p>

                <textarea
                    id="customer_notes"
                    name="customer_notes"
                    rows="3"
                    maxlength="1000"
                    placeholder="Escribe aquí tus indicaciones..."
                    class="mt-4 w-full resize-y rounded-xl border border-gray-200 bg-[#FDFCFB] px-4 py-3 text-sm outline-none transition focus:border-[#982E26] focus:ring-2 focus:ring-red-100"
                >{{ old('customer_notes') }}</textarea>

            </section>

        </div>

        {{-- =============================================
             RESUMEN Y CONFIRMACIÓN
        ============================================== --}}

        <aside class="min-w-0 lg:sticky lg:top-24">

            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">

                <div class="border-b border-gray-100 p-5">

                    <p class="text-xs font-bold uppercase tracking-widest text-[#982E26]">
                        Tu pedido
                    </p>

                    <h2 class="mt-1 text-xl font-black text-gray-950">
                        Confirmación
                    </h2>

                </div>

                <div class="p-5">

                    <div class="space-y-3 text-sm">

                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">
                                Método de entrega
                            </span>

                            <span class="font-bold text-gray-900">
                                Delivery
                            </span>
                        </div>

                        <div class="flex justify-between gap-3">
                            <span class="text-gray-500">
                                Método de pago
                            </span>

                            <span
                                id="selected-payment-label"
                                class="font-bold text-gray-900"
                            >
                                {{ $selectedPayment === 'yape'
                                    ? 'Yape'
                                    : 'Efectivo' }}
                            </span>
                        </div>

                    </div>

                    @if($quote)

                        <div class="mt-5 space-y-3 border-t border-gray-100 pt-5 text-sm">

                            <div class="flex justify-between gap-3">
                                <span class="text-gray-500">
                                    Productos
                                </span>

                                <strong>
                                    S/ {{ number_format((float) $quote['subtotal'], 2) }}
                                </strong>
                            </div>

                            <div class="flex justify-between gap-3">
                                <span class="text-gray-500">
                                    Delivery
                                </span>

                                <strong>
                                    S/ {{ number_format((float) $quote['delivery_fee'], 2) }}
                                </strong>
                            </div>

                            <div class="flex items-center justify-between gap-3 border-t border-gray-100 pt-4">

                                <span class="font-black">
                                    Total cotizado
                                </span>

                                <strong class="text-xl text-[#982E26]">
                                    S/ {{ number_format((float) $quote['total'], 2) }}
                                </strong>

                            </div>

                        </div>

                    @else

                        <div class="mt-5 rounded-xl bg-[#FFF8F4] p-4">
                            <p class="text-xs leading-6 text-[#78533D]">
                                Calcula la cotización para conocer
                                el importe del delivery y el total
                                antes de confirmar.
                            </p>
                        </div>

                    @endif

                    {{-- MANTENER AMBAS RUTAS DEL CHECKOUT --}}
                    <div class="mt-6 space-y-3">

                        <button
                            type="submit"
                            id="quote-button"
                            class="flex min-h-12 w-full items-center justify-center rounded-xl border border-[#982E26] bg-white px-5 py-3 text-center text-sm font-black text-[#982E26] transition hover:bg-[#FFF4F0]"
                        >
                            Calcular cotización
                        </button>

                        <button
                            type="submit"
                            id="confirm-button"
                            formaction="{{ route('cart.checkout.confirm') }}"
                            class="flex min-h-12 w-full items-center justify-center rounded-xl bg-[#982E26] px-5 py-3 text-center text-sm font-black text-white transition hover:bg-[#B5382E]"
                        >
                            Confirmar mi pedido
                        </button>

                    </div>

                    <p class="mt-4 text-xs leading-6 text-gray-500">
                        Al confirmar, registraremos tu pedido.
                        Los pagos por Yape requieren verificación.
                    </p>

                    <a
                        href="{{ route('cart.index') }}"
                        class="mt-4 block text-center text-xs font-semibold text-gray-500 hover:text-[#982E26]"
                    >
                        Volver a mi carrito
                    </a>

                </div>
            </div>

        </aside>

    </form>

</section>

{{-- =============================================
     MODAL PARA AMPLIAR QR
============================================== --}}

@if($qrUrl)

    <dialog
        id="qr-dialog"
        aria-label="Código QR ampliado de Yape"
        class="m-auto max-h-[95vh] w-[min(92vw,540px)] max-w-none rounded-3xl border-0 bg-white p-4 shadow-2xl backdrop:bg-black/80 sm:p-6"
    >
        <div class="flex items-center justify-between gap-3">

            <div>
                <h2 class="text-lg font-black text-gray-950">
                    Código QR de Yape
                </h2>

                <p class="mt-1 text-xs text-gray-500">
                    Escanea o toma una captura.
                </p>
            </div>

            <button
                type="button"
                id="close-qr"
                aria-label="Cerrar código QR"
                class="flex h-10 w-10 items-center justify-center rounded-full bg-gray-100 text-xl font-bold text-gray-700"
            >
                ×
            </button>

        </div>

        <div class="mt-5 flex justify-center rounded-2xl bg-white p-2">

            <img
                src="{{ $qrUrl }}"
                alt="Código QR oficial ampliado de Yape"
                class="max-h-[65vh] w-full object-contain"
            >

        </div>

        @if($paymentHolder !== '')
            <p class="mt-4 text-center text-sm font-bold text-gray-800">
                {{ $paymentHolder }}
            </p>
        @endif

    </dialog>

@endif

{{-- =============================================
     JAVASCRIPT
============================================== --}}

<script>
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    const form = document.getElementById('checkout-form');

    const paymentInputs = document.querySelectorAll(
        'input[name="payment_method"]'
    );

    const cashPanel = document.getElementById('cash-panel');
    const yapePanel = document.getElementById('yape-panel');

    const cashType = document.getElementById('cash_payment_type');

    const cashChangePanel = document.getElementById(
        'cash-change-panel'
    );

    const cashAmount = document.getElementById(
        'cash_received_amount'
    );

    const paymentLabel = document.getElementById(
        'selected-payment-label'
    );

    function currentPayment() {
        return document.querySelector(
            'input[name="payment_method"]:checked'
        )?.value ?? 'cash';
    }

    function updateCashFields() {
        const cashSelected = currentPayment() === 'cash';

        cashPanel.classList.toggle(
            'hidden',
            !cashSelected
        );

        yapePanel.classList.toggle(
            'hidden',
            cashSelected
        );

        cashType.disabled = !cashSelected;

        const requiresChange =
            cashSelected && cashType.value === 'change';

        cashChangePanel.classList.toggle(
            'hidden',
            !requiresChange
        );

        cashAmount.disabled = !requiresChange;
        cashAmount.required = requiresChange;

        paymentLabel.textContent = cashSelected
            ? 'Efectivo'
            : 'Yape';

        document.querySelectorAll('[data-payment-card]')
            .forEach(card => {
                const selected =
                    card.dataset.paymentCard === currentPayment();

                card.classList.toggle(
                    'border-[#982E26]',
                    selected
                );

                card.classList.toggle(
                    'bg-[#FFF8F5]',
                    selected
                );

                card.classList.toggle(
                    'border-gray-200',
                    !selected
                );

                card.classList.toggle(
                    'bg-white',
                    !selected
                );
            });
    }

    paymentInputs.forEach(input => {
        input.addEventListener('change', updateCashFields);
    });

    cashType.addEventListener('change', updateCashFields);

    updateCashFields();

    // ==========================================
    // AMPLIAR CÓDIGO QR
    // ==========================================

    const qrDialog = document.getElementById('qr-dialog');
    const openQr = document.getElementById('open-qr');
    const closeQr = document.getElementById('close-qr');

    if (qrDialog && openQr && closeQr) {
        openQr.addEventListener('click', () => {
            qrDialog.showModal();
        });

        closeQr.addEventListener('click', () => {
            qrDialog.close();
        });

        qrDialog.addEventListener('click', event => {
            if (event.target === qrDialog) {
                qrDialog.close();
            }
        });
    }

    // ==========================================
    // COPIAR NÚMERO DE YAPE
    // ==========================================

    const copyButton = document.getElementById(
        'copy-yape-number'
    );

    const copyFeedback = document.getElementById(
        'copy-feedback'
    );

    if (copyButton) {
        copyButton.addEventListener('click', async () => {
            const number = document.getElementById(
                'yape-number'
            )?.textContent.trim();

            if (!number) {
                return;
            }

            try {
                if (!navigator.clipboard?.writeText) {
                    throw new Error('Clipboard no disponible');
                }

                await navigator.clipboard.writeText(number);

                copyFeedback.textContent =
                    '✓ Número copiado. Pégalo en Yape.';

                copyFeedback.className =
                    'mt-2 text-xs font-semibold text-green-700';

            } catch (error) {
                copyFeedback.textContent =
                    'No se pudo copiar automáticamente. Mantén presionado el número para copiarlo.';

                copyFeedback.className =
                    'mt-2 text-xs text-amber-700';
            }
        });
    }

    // ==========================================
    // MAPA DE DELIVERY
    // ==========================================

    const mapElement = document.getElementById(
        'delivery-map'
    );

    const latInput = document.getElementById(
        'delivery_latitude'
    );

    const lngInput = document.getElementById(
        'delivery_longitude'
    );

    const locationStatus = document.getElementById(
        'location-status'
    );

    const currentLocationButton = document.getElementById(
        'btn-current-location'
    );

    const selectMapButton = document.getElementById(
        'btn-select-map'
    );

    const confirmLocationButton = document.getElementById(
        'btn-confirm-location'
    );

    let confirmedLocation = false;
    let selectedPoint = null;
    let marker = null;
    let map = null;

    if (mapElement && window.L) {
        const L = window.L;

        // Solo es un centro inicial del mapa.
        // No representa la ubicación del cliente.
        map = L.map(mapElement).setView(
            [-12.0464, -77.0428],
            12
        );

        L.tileLayer(
            'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
            {
                maxZoom: 19,
                referrerPolicy: 'origin',
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright" rel="noopener noreferrer">OpenStreetMap</a> contributors'
            }
        ).addTo(map);

        function clearLocationConfirmation() {
            confirmedLocation = false;

            latInput.value = '';
            lngInput.value = '';

            locationStatus.textContent =
                'Ubicación seleccionada. Confirma el punto en el mapa.';

            locationStatus.className =
                'mt-3 rounded-xl bg-amber-50 px-4 py-3 text-xs leading-5 text-amber-800';
        }

        function setPoint(lat, lng, zoom = 17) {
            if (
                !Number.isFinite(lat)
                || !Number.isFinite(lng)
                || Math.abs(lat) > 90
                || Math.abs(lng) > 180
            ) {
                return;
            }

            selectedPoint = {lat, lng};

            if (marker) {
                marker.setLatLng([lat, lng]);
            } else {
                marker = L.marker(
                    [lat, lng],
                    {draggable: true}
                ).addTo(map);

                marker.on('dragend', () => {
                    const position = marker.getLatLng();

                    selectedPoint = {
                        lat: position.lat,
                        lng: position.lng
                    };

                    clearLocationConfirmation();
                });
            }

            map.setView([lat, lng], zoom);

            clearLocationConfirmation();
        }

        map.on('click', event => {
            setPoint(
                event.latlng.lat,
                event.latlng.lng,
                map.getZoom()
            );
        });

        currentLocationButton.addEventListener('click', () => {
            if (!navigator.geolocation) {
                locationStatus.textContent =
                    'Tu navegador no admite geolocalización. Selecciona el punto en el mapa.';
                return;
            }

            locationStatus.textContent =
                'Buscando tu ubicación...';

            navigator.geolocation.getCurrentPosition(
                position => {
                    setPoint(
                        position.coords.latitude,
                        position.coords.longitude
                    );

                    locationStatus.textContent =
                        'Ubicación detectada. Revisa el marcador y confírmala.';
                },
                () => {
                    locationStatus.textContent =
                        'No pudimos obtener tu ubicación. Puedes seleccionar el punto en el mapa.';
                },
                {
                    enableHighAccuracy: true,
                    timeout: 15000,
                    maximumAge: 0
                }
            );
        });

        selectMapButton.addEventListener('click', () => {
            mapElement.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            map.invalidateSize();

            locationStatus.textContent =
                'Toca el mapa o arrastra el marcador al punto exacto.';
        });

        confirmLocationButton.addEventListener('click', () => {
            if (!selectedPoint) {
                locationStatus.textContent =
                    'Selecciona primero un punto en el mapa.';
                return;
            }

            latInput.value = selectedPoint.lat.toFixed(7);
            lngInput.value = selectedPoint.lng.toFixed(7);

            confirmedLocation = true;

            locationStatus.textContent =
                '✓ Ubicación confirmada correctamente.';

            locationStatus.className =
                'mt-3 rounded-xl bg-green-50 px-4 py-3 text-xs font-semibold text-green-700';
        });

        // Restaurar coordenadas tras validar o cotizar.
        const previousLat = Number(latInput.value);
        const previousLng = Number(lngInput.value);

        if (
            latInput.value !== ''
            && lngInput.value !== ''
            && Number.isFinite(previousLat)
            && Number.isFinite(previousLng)
            && Math.abs(previousLat) <= 90
            && Math.abs(previousLng) <= 180
        ) {
            setPoint(previousLat, previousLng);

            latInput.value = previousLat.toFixed(7);
            lngInput.value = previousLng.toFixed(7);

            confirmedLocation = true;

            locationStatus.textContent =
                '✓ Ubicación restaurada y confirmada.';
        }

        setTimeout(() => {
            map.invalidateSize({
                animate: false,
                pan: false
            });
        }, 300);

    } else {
        locationStatus.textContent =
            'No se pudo cargar el mapa. Recarga la página para seleccionar tu ubicación.';
    }

    // ==========================================
    // VALIDAR ENVÍO
    // ==========================================

    let submitting = false;

    form.addEventListener('submit', event => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        if (!confirmedLocation) {
            event.preventDefault();

            locationStatus.textContent =
                'Debes seleccionar y confirmar tu ubicación de entrega.';

            locationStatus.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
            });

            return;
        }

        if (
            currentPayment() === 'cash'
            && cashType.value === 'change'
            && (!cashAmount.value || Number(cashAmount.value) <= 0)
        ) {
            event.preventDefault();

            cashAmount.focus();
            return;
        }

        submitting = true;

        const submitButton = event.submitter;

        if (submitButton) {
            submitButton.disabled = true;

            submitButton.textContent =
                submitButton.id === 'quote-button'
                    ? 'Calculando...'
                    : 'Registrando pedido...';
        }
    });
});
</script>

@endsection
