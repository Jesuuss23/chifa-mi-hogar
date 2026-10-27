
@extends('layouts.storefront')

@section('title', $product->name . ' | Chifa Mi Hogar')

@section(
    'meta-description',
    \Illuminate\Support\Str::limit(
        strip_tags(
            $product->description
            ?: 'Disfruta de nuestros platos de chifa preparados especialmente para ti.'
        ),
        155
    )
)

@php
    /*
    |--------------------------------------------------------------------------
    | Datos del catálogo
    |--------------------------------------------------------------------------
    */

    $product->loadMissing([
        'category',
        'mediaAsset',
        'extras.extraCategory',
        'extras.mediaAsset',
        'recommendations.extras',
        'recommendations.mediaAsset',
    ]);

    $basePriceCents = (int) round(
        (float) $product->price * 100
    );

    $availableExtras = $product->extras
        ->filter(fn ($extra) => $extra->is_available)
        ->sortBy([
            ['sort_order', 'asc'],
            ['name', 'asc'],
        ])
        ->values();

    $allowedExtraIds = $availableExtras
        ->pluck('id')
        ->map(fn ($id) => (int) $id)
        ->all();

    $extraGroups = $availableExtras->groupBy(
        fn ($extra) =>
            $extra->extraCategory?->name
            ?? 'Otros adicionales'
    );

    $recommendations = $product->recommendations
        ->filter(function ($recommendation) use ($allowedExtraIds) {
            if (!$recommendation->is_active) {
                return false;
            }

            if ($recommendation->extras->count() > 30) {
                return false;
            }

            return $recommendation->extras->every(
                fn ($extra) =>
                    $extra->is_available
                    && in_array(
                        (int) $extra->id,
                        $allowedExtraIds,
                        true
                    )
                    && (int) $extra->pivot->quantity >= 1
                    && (int) $extra->pivot->quantity <= 50
            );
        })
        ->sortBy('sort_order')
        ->values();

    /*
    |--------------------------------------------------------------------------
    | Datos seguros para JavaScript
    |--------------------------------------------------------------------------
    */

    $extrasJson = $availableExtras->map(
        fn ($extra) => [
            'id' => (int) $extra->id,
            'name' => $extra->name,
            'priceCents' => (int) round(
                (float) $extra->price * 100
            ),
        ]
    )->values()->all();

    $recommendationsJson = $recommendations->map(
        fn ($recommendation) => [
            'id' => (int) $recommendation->id,
            'name' => $recommendation->name,
            'extras' => $recommendation->extras
                ->mapWithKeys(
                    fn ($extra) => [
                        (string) $extra->id =>
                            (int) $extra->pivot->quantity,
                    ]
                )
                ->all(),
        ]
    )->values()->all();
@endphp

@section('content')

<section
    id="product-configurator"
    class="mx-auto max-w-7xl px-4 pb-16 pt-6 sm:px-6 lg:pt-9"
>

    {{-- Regresar a la carta --}}

    <a
        href="{{ route('storefront.index') }}"
        class="mb-7 inline-flex items-center gap-2 text-sm font-semibold text-[#982E26] transition hover:opacity-75"
    >
        <span aria-hidden="true">←</span>
        Todos los productos
    </a>

    {{-- Errores del servidor --}}

    @if($errors->any())
        <div
            role="alert"
            class="mb-6 rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
        >
            {{ $errors->first() }}
        </div>
    @endif

    {{-- Errores del configurador --}}

    <div
        id="configurator-error"
        role="alert"
        aria-live="assertive"
        class="mb-6 hidden rounded-2xl border border-red-200 bg-red-50 p-4 text-sm text-red-700"
    ></div>

    <form
        id="product-form"
        method="POST"
        action="{{ route('cart.store') }}"
    >
        @csrf

        <input
            type="hidden"
            name="product_id"
            value="{{ $product->id }}"
        >

        <input
            type="hidden"
            id="form-quantity"
            name="quantity"
            value="1"
        >

        <div id="generated-fields"></div>

        {{-- =====================================================
             SECCIÓN 1: PRODUCTO PRINCIPAL
        ====================================================== --}}

        <div class="grid items-start gap-7 lg:grid-cols-2 lg:gap-12">

            {{-- Fotografía principal --}}

            <div class="min-w-0">

                <div
                    class="mx-auto w-full max-w-[530px] overflow-hidden rounded-[22px] bg-[#F4EFE9] lg:mx-0"
                >
                    @if($product->mediaAsset || $product->image_path)

                        <x-catalog-image
                            :item="$product"
                            variant="detail"
                            loading="eager"
                            class="h-64 w-full object-cover sm:h-80 lg:h-[350px]"
                        />

                    @else

                        <div
                            class="flex h-64 items-center justify-center text-sm text-gray-400 sm:h-80"
                        >
                            Fotografía próximamente
                        </div>

                    @endif
                </div>

            </div>

            {{-- Información del producto --}}

            <div class="min-w-0 lg:pt-3">

                <span
                    class="text-xs font-bold uppercase tracking-[0.15em] text-[#982E26]"
                >
                    {{ $product->category?->name ?? 'Nuestra carta' }}
                </span>

                <h1
                    class="mt-3 text-3xl font-black leading-tight tracking-tight text-[#201E1D] sm:text-4xl"
                >
                    {{ $product->name }}
                </h1>

                <p class="mt-3 max-w-lg text-sm leading-7 text-gray-500 sm:text-base">
                    {{ $product->description ?: 'Preparado con nuestro sabor especial.' }}
                </p>

                <p
                    class="mt-6 text-3xl font-black tracking-tight text-[#982E26]"
                >
                    S/ {{ number_format((float) $product->price, 2) }}
                </p>

                {{-- Selector de cantidad --}}

                <div
                    class="mt-7 flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-gray-200 bg-white px-4 py-4 sm:px-5"
                >
                    <div>
                        <h2 class="text-sm font-bold text-gray-900">
                            Cantidad total
                        </h2>

                        <p class="mt-1 text-xs text-gray-500">
                            Elige cuántos platos deseas.
                        </p>
                    </div>

                    <div
                        class="inline-flex items-center gap-3 rounded-xl border border-gray-200 bg-[#FAF8F5] p-1.5"
                    >
                        <button
                            type="button"
                            data-action="quantity-minus"
                            aria-label="Disminuir cantidad"
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-white text-xl font-bold text-gray-700 transition hover:bg-gray-100 disabled:opacity-40"
                        >
                            −
                        </button>

                        <output
                            id="quantity-output"
                            class="min-w-7 text-center text-base font-black tabular-nums"
                        >
                            1
                        </output>

                        <button
                            type="button"
                            data-action="quantity-plus"
                            aria-label="Aumentar cantidad"
                            class="flex h-9 w-9 items-center justify-center rounded-lg bg-[#982E26] text-xl font-bold text-white transition hover:bg-[#B5382E] disabled:opacity-40"
                        >
                            +
                        </button>
                    </div>
                </div>

                <p
                    id="quantity-help"
                    class="mt-3 text-xs leading-5 text-gray-500"
                >
                    Puedes añadir tus complementos favoritos más abajo.
                </p>

            </div>

        </div>

        {{-- =====================================================
             SECCIÓN 2: CONFIGURACIÓN DEL PEDIDO
        ====================================================== --}}

        <section
            id="configuration-section"
            class="mt-10 scroll-mt-24 border-t border-gray-200 pt-8 sm:mt-12"
        >

            <div class="mb-6">

                <p
                    class="text-xs font-bold uppercase tracking-[0.16em] text-[#982E26]"
                >
                    A tu gusto
                </p>

                <h2
                    id="configuration-heading"
                    class="mt-2 text-2xl font-black tracking-tight text-[#201E1D]"
                >
                    Personaliza tu plato
                </h2>

                <p
                    id="configuration-description"
                    class="mt-2 text-sm leading-6 text-gray-500"
                >
                    Agrega los complementos que prefieras.
                </p>

            </div>

            <div
                class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]"
            >

                {{-- CONTENIDO PRINCIPAL --}}

                <div class="min-w-0">

                    {{-- Pestañas: solamente para 2 o más platos --}}

                    <div
                        id="multiple-plates-panel"
                        class="mb-5 hidden"
                    >
                        <div
                            class="overflow-hidden rounded-2xl border border-gray-200 bg-white"
                        >
                            <div
                                class="border-b border-gray-100 px-4 py-3 sm:px-5"
                            >
                                <p class="text-sm font-bold text-gray-900">
                                    Detalles de tus platos
                                </p>

                                <p class="mt-1 text-xs text-gray-500">
                                    Selecciona cuál deseas configurar.
                                </p>
                            </div>

                            <div
                                id="plate-tabs"
                                class="flex gap-2 overflow-x-auto p-3"
                                role="group"
                                aria-label="Seleccionar plato"
                                style="scrollbar-width: thin;"
                            ></div>

                        </div>
                    </div>

                    {{-- Editor único que cambia según el plato --}}

                    <div
                        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                    >

                        {{-- Encabezado del editor --}}

                        <div
                            class="border-b border-gray-100 px-4 py-4 sm:px-5"
                        >

                            <div
                                class="flex flex-wrap items-start justify-between gap-3"
                            >

                                <div>

                                    <h3
                                        id="active-plate-title"
                                        class="text-base font-black text-gray-900"
                                    >
                                        Personaliza tu plato
                                    </h3>

                                    <p
                                        id="active-plate-description"
                                        class="mt-1 text-xs leading-5 text-gray-500"
                                    >
                                        Configuración original
                                    </p>

                                </div>

                                <span
                                    id="active-plate-price"
                                    class="text-lg font-black text-[#982E26]"
                                >
                                    S/ {{ number_format((float) $product->price, 2) }}
                                </span>

                            </div>

                            {{-- Copiar primera configuración --}}

                            <div
                                id="copy-configuration-panel"
                                class="mt-4 hidden"
                            >
                                <button
                                    type="button"
                                    data-action="copy-first"
                                    class="inline-flex items-center gap-2 rounded-lg border border-[#E8D5D0] bg-[#FFF8F5] px-3 py-2.5 text-xs font-bold text-[#982E26] transition hover:bg-[#FCECE7]"
                                >
                                    <span aria-hidden="true">⧉</span>
                                    Mantener configuración del Plato 1
                                </button>
                            </div>

                        </div>

                        {{-- =====================================
                             RECOMENDACIONES COMPACTAS
                        ====================================== --}}

                        <div
                            class="border-b border-gray-100 px-4 py-5 sm:px-5"
                        >

                            <div class="mb-4">

                                <h4 class="text-sm font-black text-gray-900">
                                    Nuestras recomendaciones
                                </h4>

                                <p class="mt-1 text-xs leading-5 text-gray-500">
                                    Agrega una configuración con un toque.
                                </p>

                            </div>

                            <div
                                id="recommendation-carousel"
                                class="flex snap-x snap-mandatory gap-3 overflow-x-auto pb-3"
                                style="scrollbar-width: thin;"
                            >

                                {{-- Configuración original --}}

                                <button
                                    type="button"
                                    data-action="recommendation"
                                    data-recommendation="base"
                                    aria-pressed="true"
                                    class="recommendation-card flex w-[205px] shrink-0 snap-start items-center gap-3 rounded-xl border-2 border-[#982E26] bg-[#FFF8F5] p-2.5 text-left transition sm:w-[225px]"
                                >

                                    <div
                                        class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-[#F1E7DD]"
                                    >
                                        @if($product->mediaAsset || $product->image_path)

                                            <x-catalog-image
                                                :item="$product"
                                                variant="thumbnail"
                                                loading="lazy"
                                                class="h-full w-full object-cover"
                                            />

                                        @else

                                            <div
                                                class="flex h-full items-center justify-center text-xl"
                                            >
                                                🍚
                                            </div>

                                        @endif
                                    </div>

                                    <div class="min-w-0 flex-1">

                                        <p
                                            class="text-xs font-bold leading-4 text-gray-900"
                                        >
                                            Plato original
                                        </p>

                                        <p class="mt-1 text-[11px] text-gray-500">
                                            Sin adicionales
                                        </p>

                                        <p
                                            class="mt-1 text-xs font-black text-[#982E26]"
                                        >
                                            S/ {{ number_format((float) $product->price, 2) }}
                                        </p>

                                    </div>

                                    <span
                                        data-selection-icon
                                        class="text-base font-black text-[#982E26]"
                                    >
                                        ✓
                                    </span>

                                </button>

                                {{-- Recomendaciones del administrador --}}

                                @foreach($recommendations as $recommendation)

                                    @php
                                        $recommendationExtraCents =
                                            $recommendation->extras->sum(
                                                fn ($extra) =>
                                                    (int) round(
                                                        (float) $extra->price * 100
                                                    ) * (int) $extra->pivot->quantity
                                            );

                                        $recommendationPrice =
                                            (
                                                $basePriceCents
                                                + $recommendationExtraCents
                                            ) / 100;
                                    @endphp

                                    <button
                                        type="button"
                                        data-action="recommendation"
                                        data-recommendation="{{ $recommendation->id }}"
                                        aria-pressed="false"
                                        class="recommendation-card flex w-[205px] shrink-0 snap-start items-center gap-3 rounded-xl border-2 border-gray-200 bg-white p-2.5 text-left transition hover:border-[#982E26] sm:w-[225px]"
                                    >

                                        <div
                                            class="h-14 w-14 shrink-0 overflow-hidden rounded-lg bg-[#F1E7DD]"
                                        >

                                            @if($recommendation->mediaAsset || $recommendation->image_path)

                                                <x-catalog-image
                                                    :item="$recommendation"
                                                    variant="thumbnail"
                                                    loading="lazy"
                                                    class="h-full w-full object-cover"
                                                />

                                            @else

                                                <div
                                                    class="flex h-full items-center justify-center text-xl"
                                                >
                                                    🍽️
                                                </div>

                                            @endif

                                        </div>

                                        <div class="min-w-0 flex-1">

                                            <p
                                                class="line-clamp-2 text-xs font-bold leading-4 text-gray-900"
                                            >
                                                {{ $recommendation->name }}
                                            </p>

                                            <p
                                                class="mt-1 text-[11px] text-gray-500"
                                            >
                                                {{ $recommendation->extras->count() }}
                                                complementos
                                            </p>

                                            <p
                                                class="mt-1 text-xs font-black text-[#982E26]"
                                            >
                                                S/ {{ number_format($recommendationPrice, 2) }}
                                            </p>

                                        </div>

                                        <span
                                            data-selection-icon
                                            class="text-base font-black text-gray-300"
                                        >
                                            ○
                                        </span>

                                    </button>

                                @endforeach

                            </div>

                            <p
                                id="recommendation-notice"
                                class="mt-2 text-xs leading-5 text-gray-500"
                            >
                                Selecciona una recomendación para añadir sus complementos.
                            </p>

                        </div>

                        {{-- =====================================
                             ADICIONALES POR CATEGORÍA
                        ====================================== --}}

                        <div class="px-4 py-5 sm:px-5">

                            <div class="mb-5">

                                <h4 class="text-sm font-black text-gray-900">
                                    Adicionales
                                </h4>

                                <p class="mt-1 text-xs leading-5 text-gray-500">
                                    Aumenta o disminuye la cantidad de cada complemento.
                                </p>

                            </div>

                            @if($availableExtras->isNotEmpty())

                                <div class="space-y-6">

                                    @foreach($extraGroups as $categoryName => $groupExtras)

                                        <section>

                                            <h5
                                                class="mb-3 text-xs font-black uppercase tracking-wide text-[#982E26]"
                                            >
                                                {{ $categoryName }}
                                            </h5>

                                            <div class="space-y-2">

                                                @foreach($groupExtras as $extra)

                                                    <div
                                                        class="flex items-center gap-3 rounded-xl border border-gray-100 bg-[#FDFBF9] p-2.5 sm:p-3"
                                                    >

                                                        {{-- Miniatura compacta --}}

                                                        <div
                                                            class="h-12 w-12 shrink-0 overflow-hidden rounded-lg bg-[#F2E9E0] sm:h-14 sm:w-14"
                                                        >

                                                            @if($extra->mediaAsset || $extra->image_path)

                                                                <x-catalog-image
                                                                    :item="$extra"
                                                                    variant="thumbnail"
                                                                    loading="lazy"
                                                                    class="h-full w-full object-cover"
                                                                />

                                                            @else

                                                                <div
                                                                    class="flex h-full items-center justify-center text-lg"
                                                                >
                                                                    🍴
                                                                </div>

                                                            @endif

                                                        </div>

                                                        {{-- Información del adicional --}}

                                                        <div class="min-w-0 flex-1">

                                                            <p
                                                                class="text-xs font-bold leading-5 text-gray-900 sm:text-sm"
                                                            >
                                                                {{ $extra->name }}
                                                            </p>

                                                            <p
                                                                class="mt-0.5 text-xs font-semibold text-[#982E26]"
                                                            >
                                                                + S/ {{ number_format((float) $extra->price, 2) }}
                                                            </p>

                                                        </div>

                                                        {{-- Selector compacto --}}

                                                        <div
                                                            class="flex shrink-0 items-center gap-1 rounded-lg border border-gray-200 bg-white p-1"
                                                        >

                                                            <button
                                                                type="button"
                                                                data-action="extra-minus"
                                                                data-extra-id="{{ $extra->id }}"
                                                                aria-label="Quitar {{ $extra->name }}"
                                                                class="flex h-8 w-8 items-center justify-center rounded-md text-lg font-bold text-gray-700 transition hover:bg-gray-100 disabled:opacity-30"
                                                            >
                                                                −
                                                            </button>

                                                            <output
                                                                data-extra-quantity="{{ $extra->id }}"
                                                                class="min-w-5 text-center text-xs font-black tabular-nums"
                                                            >
                                                                0
                                                            </output>

                                                            <button
                                                                type="button"
                                                                data-action="extra-plus"
                                                                data-extra-id="{{ $extra->id }}"
                                                                aria-label="Añadir {{ $extra->name }}"
                                                                class="flex h-8 w-8 items-center justify-center rounded-md bg-[#982E26] text-lg font-bold text-white transition hover:bg-[#B5382E] disabled:opacity-30"
                                                            >
                                                                +
                                                            </button>

                                                        </div>

                                                    </div>

                                                @endforeach

                                            </div>

                                        </section>

                                    @endforeach

                                </div>

                            @else

                                <p
                                    class="rounded-xl bg-gray-50 p-4 text-sm text-gray-500"
                                >
                                    Este plato no tiene adicionales disponibles.
                                </p>

                            @endif

                        </div>

                        {{-- Subtotal del plato activo --}}

                        <div
                            class="flex items-center justify-between gap-4 border-t border-gray-100 bg-[#FFF9F5] px-4 py-4 sm:px-5"
                        >

                            <span
                                id="active-subtotal-label"
                                class="text-sm font-semibold text-gray-700"
                            >
                                Subtotal del plato
                            </span>

                            <output
                                id="active-subtotal"
                                aria-live="polite"
                                class="text-lg font-black text-[#982E26]"
                            >
                                S/ {{ number_format((float) $product->price, 2) }}
                            </output>

                        </div>

                    </div>

                </div>

                {{-- =============================================
                     RESUMEN LATERAL
                ============================================== --}}

                <aside class="min-w-0 lg:sticky lg:top-24">

                    <div
                        class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm"
                    >

                        <div
                            class="border-b border-gray-100 px-5 py-4"
                        >

                            <h2
                                class="text-lg font-black text-gray-900"
                            >
                                Resumen del pedido
                            </h2>

                            <p
                                id="summary-quantity"
                                class="mt-1 text-xs text-gray-500"
                            >
                                1 plato seleccionado
                            </p>

                        </div>

                        <div class="p-5">

                            {{-- Resumen individual --}}

                            <div
                                id="order-summary-items"
                                class="space-y-4"
                            ></div>

                            {{-- Total --}}

                            <div
                                class="mt-5 border-t border-gray-200 pt-5"
                            >

                                <div
                                    class="flex items-center justify-between gap-3"
                                >

                                    <span
                                        class="font-bold text-gray-900"
                                    >
                                        Total
                                    </span>

                                    <output
                                        id="order-total"
                                        aria-live="polite"
                                        class="text-2xl font-black text-[#982E26]"
                                    >
                                        S/ {{ number_format((float) $product->price, 2) }}
                                    </output>

                                </div>

                                <p
                                    class="mt-2 text-xs leading-5 text-gray-500"
                                >
                                    El delivery se calcula al confirmar tu dirección.
                                </p>

                            </div>

                            {{-- Agregar al carrito --}}

                            <button
                                id="add-cart-button"
                                type="submit"
                                disabled
                                class="mt-6 flex min-h-12 w-full items-center justify-center gap-2 rounded-xl bg-[#982E26] px-4 py-3 text-sm font-black text-white transition hover:bg-[#B5382E] disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                <span aria-hidden="true">🛒</span>
                                Agregar al carrito
                            </button>

                            <a
                                href="{{ route('storefront.index') }}"
                                class="mt-4 block text-center text-xs font-semibold text-gray-500 transition hover:text-[#982E26]"
                            >
                                Seguir comprando
                            </a>

                        </div>

                    </div>

                </aside>

            </div>

        </section>

    </form>

    <noscript>
        <div
            class="mt-5 rounded-xl bg-amber-50 p-4 text-sm text-amber-800"
        >
            Activa JavaScript para personalizar tus platos.
        </div>
    </noscript>

</section>

{{-- =========================================================
     LÓGICA JAVASCRIPT
========================================================== --}}

<script>
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    /*
    |--------------------------------------------------------------------------
    | Datos enviados desde Laravel
    |--------------------------------------------------------------------------
    */

    const PRODUCT_ID = @json((int) $product->id);
    const PRODUCT_NAME = @json($product->name);

    const BASE_CENTS = @json($basePriceCents);

    const EXTRAS_DATA = @json($extrasJson);
    const RECOMMENDATIONS_DATA = @json($recommendationsJson);

    const MAX_PLATES = 20;
    const MAX_EXTRA_QUANTITY = 50;
    const MAX_EXTRA_TYPES = 30;

    const root = document.getElementById('product-configurator');

    const form = document.getElementById('product-form');

    const generatedFields =
        document.getElementById('generated-fields');

    const $ = id => document.getElementById(id);

    const extrasById = new Map(
        EXTRAS_DATA.map(extra => [
            String(extra.id),
            extra
        ])
    );

    const recommendationsById = new Map(
        RECOMMENDATIONS_DATA.map(recommendation => [
            String(recommendation.id),
            recommendation
        ])
    );

    /*
    |--------------------------------------------------------------------------
    | Estructura de cada plato
    |--------------------------------------------------------------------------
    |
    | Cada plato tiene sus propios adicionales.
    |
    | Producto original:
    |   type: product
    |   extras: {}
    |
    | Recomendación:
    |   type: recommendation
    |   extras: cantidades preconfiguradas
    |
    */

    function createOriginalPlate() {
        return {
            type: 'product',
            id: PRODUCT_ID,
            mode: 'edit',
            extras: {}
        };
    }

    function clonePlate(plate) {
        return {
            type: plate.type,
            id: plate.id,
            mode: plate.mode,
            extras: {
                ...plate.extras
            }
        };
    }

    let plates = [
        createOriginalPlate()
    ];

    let activePlateIndex = 0;

    function activePlate() {
        return plates[activePlateIndex];
    }

    function money(cents) {
        return 'S/ ' + (cents / 100).toFixed(2);
    }

    function getRecommendation(id) {
        return recommendationsById.get(String(id));
    }

    function getPlateName(plate) {
        if (plate.type === 'recommendation') {
            return getRecommendation(plate.id)?.name
                ?? PRODUCT_NAME;
        }

        return PRODUCT_NAME;
    }

    function getPlateExtrasDescription(plate) {
        const parts = [];

        for (const [id, quantity] of Object.entries(plate.extras)) {
            const extra = extrasById.get(String(id));

            if (!extra || quantity < 1) {
                continue;
            }

            parts.push(
                `${quantity} × ${extra.name}`
            );
        }

        return parts.length
            ? parts.join(', ')
            : 'Sin adicionales';
    }

    function plateTotalCents(plate) {
        let total = BASE_CENTS;

        for (const [id, quantity] of Object.entries(plate.extras)) {
            const extra = extrasById.get(String(id));

            if (!extra) {
                continue;
            }

            total += extra.priceCents * quantity;
        }

        return total;
    }

    function orderTotalCents() {
        return plates.reduce(
            (total, plate) => total + plateTotalCents(plate),
            0
        );
    }

    function normalizeExtras(extras) {
        const result = {};

        for (const [id, originalQuantity] of Object.entries(extras || {})) {
            const quantity = Number(originalQuantity);

            if (
                !extrasById.has(String(id))
                || !Number.isInteger(quantity)
                || quantity < 1
                || quantity > MAX_EXTRA_QUANTITY
            ) {
                continue;
            }

            result[String(id)] = quantity;
        }

        return result;
    }

    /*
    |--------------------------------------------------------------------------
    | Mensajes
    |--------------------------------------------------------------------------
    */

    function showError(message) {
        const box = $('configurator-error');

        box.textContent = message;
        box.classList.remove('hidden');

        box.scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });
    }

    function clearError() {
        const box = $('configurator-error');

        box.textContent = '';
        box.classList.add('hidden');
    }

    /*
    |--------------------------------------------------------------------------
    | 1. Cambiar cantidad total
    |--------------------------------------------------------------------------
    |
    | Regla principal:
    | Cuando se añade otro plato, comienza desde cero.
    |
    | No copiamos automáticamente la configuración anterior.
    |
    */

    function changeQuantity(delta) {
        const newQuantity = plates.length + delta;

        if (
            newQuantity < 1
            || newQuantity > MAX_PLATES
        ) {
            return;
        }

        if (delta > 0) {
            plates.push(
                createOriginalPlate()
            );
        } else {
            plates.pop();

            if (activePlateIndex >= plates.length) {
                activePlateIndex = plates.length - 1;
            }
        }

        clearError();
        render();
    }

    /*
    |--------------------------------------------------------------------------
    | 2. Seleccionar plato
    |--------------------------------------------------------------------------
    */

    function selectPlate(index) {
        if (
            index < 0
            || index >= plates.length
        ) {
            return;
        }

        activePlateIndex = index;

        clearError();
        render();
    }

    /*
    |--------------------------------------------------------------------------
    | 3. Seleccionar recomendación del plato activo
    |--------------------------------------------------------------------------
    |
    | No cambia los otros platos.
    |
    | Si elige una recomendación:
    | - Se carga la configuración del administrador.
    |
    | Si elige plato original:
    | - Extras vuelven a cero.
    |
    */

    function selectRecommendation(value) {
        const plate = activePlate();

        if (String(value) === 'base') {
            plates[activePlateIndex] =
                createOriginalPlate();

            clearError();
            render();

            return;
        }

        const recommendation = getRecommendation(value);

        if (!recommendation) {
            return;
        }

        plates[activePlateIndex] = {
            type: 'recommendation',
            id: recommendation.id,
            mode: 'keep',
            extras: normalizeExtras(
                recommendation.extras
            )
        };

        clearError();
        render();
    }

    /*
    |--------------------------------------------------------------------------
    | 4. Modificar adicionales
    |--------------------------------------------------------------------------
    |
    | Cuando una recomendación se modifica,
    | pasa automáticamente a mode: edit.
    |
    | No existe botón extra de "Editar".
    |
    */

    function changeExtra(id, delta) {
        const key = String(id);

        if (!extrasById.has(key)) {
            return;
        }

        const plate = activePlate();

        const previousQuantity = Number(
            plate.extras[key] || 0
        );

        const nextQuantity = Math.max(
            0,
            Math.min(
                MAX_EXTRA_QUANTITY,
                previousQuantity + delta
            )
        );

        if (
            previousQuantity === 0
            && nextQuantity > 0
            && Object.keys(plate.extras).length >= MAX_EXTRA_TYPES
        ) {
            showError(
                'Puedes elegir como máximo 30 tipos de adicionales por plato.'
            );

            return;
        }

        if (nextQuantity === 0) {
            delete plate.extras[key];
        } else {
            plate.extras[key] = nextQuantity;
        }

        if (plate.type === 'recommendation') {
            plate.mode = 'edit';
        }

        clearError();
        render();
    }

    /*
    |--------------------------------------------------------------------------
    | 5. Copiar configuración del Plato 1
    |--------------------------------------------------------------------------
    |
    | Solo aparece desde Plato 2.
    |
    */

    function copyFirstPlate() {
        if (activePlateIndex === 0) {
            return;
        }

        plates[activePlateIndex] =
            clonePlate(plates[0]);

        clearError();
        render();
    }

    /*
    |--------------------------------------------------------------------------
    | 6. Actualizar cantidad visual
    |--------------------------------------------------------------------------
    */

    function renderQuantity() {
        const quantity = plates.length;

        $('quantity-output').textContent = quantity;
        $('form-quantity').value = quantity;

        $('quantity-help').textContent =
            quantity === 1
                ? 'Puedes personalizar tu plato con los adicionales disponibles.'
                : `Personaliza cada uno de tus ${quantity} platos de forma independiente.`;

        root.querySelector(
            '[data-action="quantity-minus"]'
        ).disabled = quantity <= 1;

        root.querySelector(
            '[data-action="quantity-plus"]'
        ).disabled = quantity >= MAX_PLATES;
    }

    /*
    |--------------------------------------------------------------------------
    | 7. Mostrar pestañas solo cuando hay 2 o más platos
    |--------------------------------------------------------------------------
    */

    function renderPlateTabs() {
        const panel = $('multiple-plates-panel');
        const tabs = $('plate-tabs');

        const isMultiple = plates.length > 1;

        panel.classList.toggle(
            'hidden',
            !isMultiple
        );

        tabs.replaceChildren();

        if (!isMultiple) {
            return;
        }

        plates.forEach((plate, index) => {
            const button = document.createElement('button');

            const selected =
                index === activePlateIndex;

            button.type = 'button';

            button.dataset.action = 'select-plate';
            button.dataset.plateIndex = String(index);

            button.setAttribute(
                'aria-pressed',
                selected ? 'true' : 'false'
            );

            button.className =
                'min-h-10 shrink-0 rounded-lg border px-4 py-2 text-sm font-bold transition ' +
                (
                    selected
                        ? 'border-[#982E26] bg-[#982E26] text-white'
                        : 'border-gray-200 bg-[#FAF8F5] text-gray-700 hover:border-[#982E26]'
                );

            button.textContent = `Plato ${index + 1}`;

            tabs.appendChild(button);
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 8. Actualizar encabezado del editor
    |--------------------------------------------------------------------------
    */

    function renderActivePlate() {
        const plate = activePlate();

        const isMultiple = plates.length > 1;

        $('configuration-heading').textContent =
            isMultiple
                ? 'Personaliza tus platos'
                : 'Personaliza tu plato';

        $('configuration-description').textContent =
            isMultiple
                ? 'Cada plato conserva su propia configuración.'
                : 'Elige una recomendación o agrega tus complementos favoritos.';

        $('active-plate-title').textContent =
            isMultiple
                ? `Configurando Plato ${activePlateIndex + 1}`
                : 'Personaliza tu plato';

        $('active-plate-description').textContent =
            plate.type === 'recommendation'
                ? `Recomendación: ${getPlateName(plate)}`
                : 'Configuración original';

        $('active-plate-price').textContent =
            money(plateTotalCents(plate));

        $('active-subtotal-label').textContent =
            isMultiple
                ? `Subtotal Plato ${activePlateIndex + 1}`
                : 'Subtotal del plato';

        $('active-subtotal').textContent =
            money(plateTotalCents(plate));

        $('copy-configuration-panel').classList.toggle(
            'hidden',
            !isMultiple || activePlateIndex === 0
        );
    }

    /*
    |--------------------------------------------------------------------------
    | 9. Carrusel: selección individual
    |--------------------------------------------------------------------------
    */

    function renderRecommendations() {
        const plate = activePlate();

        const selectedValue =
            plate.type === 'recommendation'
                ? String(plate.id)
                : 'base';

        root.querySelectorAll('.recommendation-card')
            .forEach(card => {
                const isSelected =
                    card.dataset.recommendation === selectedValue;

                card.setAttribute(
                    'aria-pressed',
                    isSelected ? 'true' : 'false'
                );

                card.classList.toggle(
                    'border-[#982E26]',
                    isSelected
                );

                card.classList.toggle(
                    'bg-[#FFF8F5]',
                    isSelected
                );

                card.classList.toggle(
                    'border-gray-200',
                    !isSelected
                );

                card.classList.toggle(
                    'bg-white',
                    !isSelected
                );

                const icon = card.querySelector(
                    '[data-selection-icon]'
                );

                if (icon) {
                    icon.textContent =
                        isSelected ? '✓' : '○';

                    icon.classList.toggle(
                        'text-[#982E26]',
                        isSelected
                    );

                    icon.classList.toggle(
                        'text-gray-300',
                        !isSelected
                    );
                }
            });

        const notice = $('recommendation-notice');

        if (plate.type === 'recommendation') {
            notice.textContent =
                plate.mode === 'keep'
                    ? 'Recomendación aplicada. Puedes cambiar las cantidades de los adicionales debajo.'
                    : 'Recomendación personalizada. Tus cambios se aplican únicamente a este plato.';
        } else {
            notice.textContent =
                'Puedes elegir una recomendación o agregar adicionales manualmente.';
        }
    }

    /*
    |--------------------------------------------------------------------------
    | 10. Actualizar controles de adicionales
    |--------------------------------------------------------------------------
    */

    function renderExtras() {
        const plate = activePlate();

        root.querySelectorAll('[data-extra-quantity]')
            .forEach(output => {
                const id = output.dataset.extraQuantity;

                output.textContent =
                    plate.extras[id] || 0;
            });

        root.querySelectorAll('[data-action="extra-minus"]')
            .forEach(button => {
                const id = button.dataset.extraId;

                button.disabled =
                    !(plate.extras[id] > 0);
            });

        root.querySelectorAll('[data-action="extra-plus"]')
            .forEach(button => {
                const id = button.dataset.extraId;

                button.disabled =
                    (plate.extras[id] || 0) >= MAX_EXTRA_QUANTITY;
            });
    }

    /*
    |--------------------------------------------------------------------------
    | 11. Resumen del pedido
    |--------------------------------------------------------------------------
    */

    function renderSummary() {
        const container = $('order-summary-items');

        container.replaceChildren();

        plates.forEach((plate, index) => {
            const article = document.createElement('article');

            article.className =
                'border-b border-gray-100 pb-3 last:border-b-0 last:pb-0';

            const top = document.createElement('div');

            top.className =
                'flex items-start justify-between gap-3';

            const details = document.createElement('div');

            details.className = 'min-w-0 flex-1';

            const name = document.createElement('p');

            name.className =
                'text-sm font-bold text-gray-900';

            name.textContent =
                plates.length === 1
                    ? PRODUCT_NAME
                    : `Plato ${index + 1}: ${PRODUCT_NAME}`;

            const recommendation = document.createElement('p');

            recommendation.className =
                'mt-1 text-xs leading-5 text-gray-500';

            recommendation.textContent =
                plate.type === 'recommendation'
                    ? getPlateName(plate)
                    : 'Plato original';

            const extras = document.createElement('p');

            extras.className =
                'mt-1 text-xs leading-5 text-gray-500';

            extras.textContent =
                getPlateExtrasDescription(plate);

            const price = document.createElement('strong');

            price.className =
                'shrink-0 text-sm font-black text-[#982E26]';

            price.textContent =
                money(plateTotalCents(plate));

            details.appendChild(name);
            details.appendChild(recommendation);
            details.appendChild(extras);

            top.appendChild(details);
            top.appendChild(price);

            article.appendChild(top);

            container.appendChild(article);
        });

        $('summary-quantity').textContent =
            plates.length === 1
                ? '1 plato seleccionado'
                : `${plates.length} platos seleccionados`;

        $('order-total').textContent =
            money(orderTotalCents());
    }

    /*
    |--------------------------------------------------------------------------
    | 12. Renderizado general
    |--------------------------------------------------------------------------
    */

    function render() {
        renderQuantity();
        renderPlateTabs();
        renderActivePlate();
        renderRecommendations();
        renderExtras();
        renderSummary();
    }

    /*
    |--------------------------------------------------------------------------
    | 13. Preparar datos para Laravel
    |--------------------------------------------------------------------------
    |
    | El backend recibe:
    |
    | product_id
    | quantity
    | units[0][type]
    | units[0][id]
    | units[0][quantity]
    | units[0][mode]
    | units[0][extras][0][id]
    | units[0][extras][0][quantity]
    |
    */

    function addHiddenField(name, value) {
        const input = document.createElement('input');

        input.type = 'hidden';
        input.name = name;
        input.value = String(value);

        generatedFields.appendChild(input);
    }

    function buildSubmission() {
        generatedFields.replaceChildren();

        plates.forEach((plate, plateIndex) => {
            const prefix = `units[${plateIndex}]`;

            addHiddenField(
                `${prefix}[type]`,
                plate.type
            );

            addHiddenField(
                `${prefix}[id]`,
                plate.id
            );

            addHiddenField(
                `${prefix}[quantity]`,
                1
            );

            addHiddenField(
                `${prefix}[mode]`,
                plate.mode
            );

            const selectedExtras = Object.entries(
                plate.extras
            ).sort(
                ([a], [b]) => Number(a) - Number(b)
            );

            selectedExtras.forEach(
                ([extraId, quantity], extraIndex) => {
                    addHiddenField(
                        `${prefix}[extras][${extraIndex}][id]`,
                        extraId
                    );

                    addHiddenField(
                        `${prefix}[extras][${extraIndex}][quantity]`,
                        quantity
                    );
                }
            );
        });
    }

    /*
    |--------------------------------------------------------------------------
    | 14. Validación del configurador
    |--------------------------------------------------------------------------
    */

    function validatePlates() {
        if (
            plates.length < 1
            || plates.length > MAX_PLATES
        ) {
            return 'La cantidad de platos no es válida.';
        }

        for (const plate of plates) {
            if (
                plate.type !== 'product'
                && plate.type !== 'recommendation'
            ) {
                return 'La configuración del plato no es válida.';
            }

            if (
                plate.type === 'recommendation'
                && !getRecommendation(plate.id)
            ) {
                return 'La recomendación seleccionada no es válida.';
            }

            if (
                plate.type === 'product'
                && Number(plate.id) !== PRODUCT_ID
            ) {
                return 'El producto seleccionado no es válido.';
            }

            const selectedExtras = Object.entries(
                plate.extras
            );

            if (selectedExtras.length > MAX_EXTRA_TYPES) {
                return 'Has seleccionado demasiados tipos de adicionales.';
            }

            for (const [id, quantity] of selectedExtras) {
                if (!extrasById.has(String(id))) {
                    return 'Uno de los adicionales no está disponible.';
                }

                if (
                    !Number.isInteger(quantity)
                    || quantity < 1
                    || quantity > MAX_EXTRA_QUANTITY
                ) {
                    return 'Una cantidad de adicionales no es válida.';
                }
            }
        }

        return null;
    }

    /*
    |--------------------------------------------------------------------------
    | 15. Eventos
    |--------------------------------------------------------------------------
    */

    root.addEventListener('click', event => {
        const button = event.target.closest(
            'button[data-action]'
        );

        if (!button || !root.contains(button)) {
            return;
        }

        switch (button.dataset.action) {
            case 'quantity-minus':
                changeQuantity(-1);
                break;

            case 'quantity-plus':
                changeQuantity(1);
                break;

            case 'select-plate':
                selectPlate(
                    Number(button.dataset.plateIndex)
                );
                break;

            case 'recommendation':
                selectRecommendation(
                    button.dataset.recommendation
                );
                break;

            case 'extra-minus':
                changeExtra(
                    button.dataset.extraId,
                    -1
                );
                break;

            case 'extra-plus':
                changeExtra(
                    button.dataset.extraId,
                    1
                );
                break;

            case 'copy-first':
                copyFirstPlate();
                break;
        }
    });

    /*
    |--------------------------------------------------------------------------
    | 16. Enviar al carrito
    |--------------------------------------------------------------------------
    */

    let submitting = false;

    form.addEventListener('submit', event => {
        if (submitting) {
            event.preventDefault();
            return;
        }

        clearError();

        const validationError = validatePlates();

        if (validationError) {
            event.preventDefault();

            showError(validationError);

            return;
        }

        buildSubmission();

        submitting = true;

        const button = $('add-cart-button');

        button.disabled = true;
        button.textContent = 'Agregando al carrito...';

        // El formulario se envía a cart.store.
        // Laravel valida los adicionales y sus precios.
    });

    /*
    |--------------------------------------------------------------------------
    | Inicio
    |--------------------------------------------------------------------------
    */

    $('add-cart-button').disabled = false;

    render();
});
</script>

@endsection
