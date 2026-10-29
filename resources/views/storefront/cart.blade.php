
@extends('layouts.storefront')

@section('title', 'Mi carrito | Chifa Mi Hogar')

@section('content')

<section class="customer-surface mx-auto max-w-6xl px-4 pb-16 pt-8 sm:px-6 lg:pt-12">

    {{-- ENCABEZADO --}}
    <div class="mb-8 flex flex-wrap items-end justify-between gap-4">

        <div>
            <p class="text-xs font-bold uppercase tracking-[0.16em] text-primary">
                Tu selección
            </p>

            <h1 class="mt-2 text-3xl font-black tracking-tight text-foreground sm:text-4xl">
                Mi carrito
            </h1>

            <p class="mt-2 text-sm text-foreground/75">
                Revisa y personaliza tus platos antes de confirmar.
            </p>
        </div>

        <a
            href="{{ route('storefront.index') }}"
            class="inline-flex items-center gap-2 text-sm font-semibold text-primary hover:underline"
        >
            ← Seguir comprando
        </a>

    </div>

    {{-- MENSAJES --}}
    @if(session('success'))
        <div
            role="status"
            class="mb-6 rounded-none border border-green-200 bg-green-50 p-4 text-sm font-medium text-green-800"
        >
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div
            role="alert"
            class="mb-6 rounded-none border border-destructive/30 bg-destructive/10 p-4 text-sm text-destructive"
        >
            {{ $errors->first() }}
        </div>
    @endif

    @if(count($cart) === 0)

        {{-- CARRITO VACÍO --}}
        <div class="rounded-none border border-border bg-card px-6 py-14 text-center shadow-storefront">

            <div
                class="mx-auto flex h-16 w-16 items-center justify-center rounded-none bg-accent text-3xl"
                aria-hidden="true"
            >
                🛒
            </div>

            <h2 class="mt-5 text-xl font-black text-foreground">
                Tu carrito está vacío
            </h2>

            <p class="mx-auto mt-2 max-w-sm text-sm leading-6 text-foreground/75">
                Todavía no agregaste ningún plato.
                Descubre nuestra carta y elige tus favoritos.
            </p>

            <a
                href="{{ route('storefront.index') }}"
                class="mt-6 inline-flex min-h-12 items-center justify-center rounded-none bg-primary px-6 py-3 text-sm font-bold text-primary-foreground transition hover:bg-primary/90"
            >
                Explorar la carta
            </a>

        </div>

    @else

        <div class="grid items-start gap-6 lg:grid-cols-[minmax(0,1fr)_320px]">

            {{-- ==========================================
                 LISTA DE PRODUCTOS
            =========================================== --}}

            <div class="min-w-0 space-y-4">

                @foreach($cart as $item)

                    @php
                        $type = $item['type'] ?? 'product';

                        $isCombo = $type === 'combo';
                        $isRecommendation = $type === 'recommendation';

                        $product = $item['product'] ?? null;
                        $combo = $item['combo'] ?? null;
                        $recommendation = $item['recommendation'] ?? null;

                        $quantity = (int) $item['quantity'];

                        $itemName = $isCombo
                            ? $combo?->name
                            : ($isRecommendation
                                ? ($item['display_name']
                                    ?? $recommendation?->name
                                    ?? $product?->name)
                                : $product?->name);

                        $displayExtras = collect($item['extras'] ?? []);

                        $selectedQuantities = $displayExtras
                            ->mapWithKeys(function ($extra) {
                                $id = (int) data_get($extra, 'id');

                                $qty = (int) (
                                    data_get($extra, 'selected_quantity')
                                    ?? data_get($extra, 'pivot.quantity')
                                    ?? 1
                                );

                                return [$id => $qty];
                            })
                            ->all();

                        /*
                         * Consultar los adicionales disponibles del plato
                         * para permitir editarlos desde este carrito.
                         */
                        $availableExtras = $product
                            ? $product->extras()
                                ->with(['extraCategory', 'mediaAsset'])
                                ->where('extras.is_available', true)
                                ->orderBy('extras.sort_order')
                                ->orderBy('extras.name')
                                ->get()
                            : collect();

                        $extraGroups = $availableExtras->groupBy(
                            fn ($extra) =>
                                $extra->extraCategory?->name
                                ?? 'Otros adicionales'
                        );

                        $comboComponents = collect(
                            $item['components'] ?? []
                        );

                        $canEdit = $isCombo
                            ? $comboComponents->isNotEmpty()
                            : $availableExtras->isNotEmpty();
                    @endphp

                    <article
                        class="overflow-hidden rounded-none border border-border bg-card shadow-storefront"
                    >

                        {{-- PRODUCTO --}}
                        <div class="p-4 sm:p-5">

                            <div class="flex items-start gap-3 sm:gap-4">

                                {{-- Imagen compacta --}}
                                <div
                                    class="h-20 w-20 shrink-0 overflow-hidden rounded-none bg-accent sm:h-24 sm:w-24"
                                >

                                    @if($isCombo)

                                        @if($combo?->mediaAsset)
                                            <img
                                                src="{{ $combo->mediaAsset->cardUrl() }}"
                                                alt="{{ $combo->name }}"
                                                loading="lazy"
                                                class="h-full w-full object-cover"
                                            />
                                        @else
                                            <div class="flex h-full items-center justify-center text-2xl">
                                                🍱
                                            </div>
                                        @endif

                                    @elseif($isRecommendation && ($recommendation?->mediaAsset || $recommendation?->image_path))

                                        <x-catalog-image
                                            :item="$recommendation"
                                            variant="thumbnail"
                                            loading="lazy"
                                            class="h-full w-full object-cover"
                                        />

                                    @elseif($product && ($product->mediaAsset || $product->image_path))

                                        <x-catalog-image
                                            :item="$product"
                                            variant="thumbnail"
                                            loading="lazy"
                                            class="h-full w-full object-cover"
                                        />

                                    @else

                                        <div class="flex h-full items-center justify-center text-2xl">
                                            🍜
                                        </div>

                                    @endif

                                </div>

                                {{-- Información principal --}}
                                <div class="min-w-0 flex-1">

                                    <div class="flex flex-wrap items-center gap-2">

                                        <h2 class="text-sm font-black leading-5 text-foreground sm:text-base">
                                            {{ $isRecommendation
                                                ? ($product?->name ?? $itemName)
                                                : $itemName }}
                                        </h2>

                                        @if($isCombo)
                                            <span class="rounded-none bg-amber-50 px-2.5 py-1 text-[10px] font-bold text-amber-800">
                                                Combo
                                            </span>
                                        @elseif($isRecommendation)
                                            <span class="rounded-none bg-accent px-2.5 py-1 text-[10px] font-bold text-primary">
                                                Recomendación
                                            </span>
                                        @endif

                                    </div>

                                    @if($isRecommendation)
                                        <p class="mt-1 text-xs font-medium text-primary">
                                            {{ $itemName }}
                                        </p>
                                    @endif

                                    <p class="mt-2 text-xs text-foreground/75">
                                        {{ $quantity }}
                                        {{ $quantity === 1 ? 'unidad' : 'unidades' }}
                                        · S/ {{ number_format((float) $item['unit_price'], 2) }} c/u
                                    </p>

                                    {{-- Adicionales seleccionados --}}
                                    @if($isCombo)

                                        <div class="mt-3 space-y-1">

                                            @foreach($comboComponents as $component)
                                                <p class="text-xs leading-5 text-foreground/75">
                                                    <span class="font-semibold">
                                                        {{ $component['selected_quantity'] }} ×
                                                    </span>

                                                    {{ $component['name'] }}

                                                    @if($component['additional_quantity'] > 0)
                                                        <span class="text-primary">
                                                            (+{{ $component['additional_quantity'] }})
                                                        </span>
                                                    @endif
                                                </p>
                                            @endforeach

                                        </div>

                                    @elseif($displayExtras->isNotEmpty())

                                        <div class="mt-3 space-y-1">

                                            @foreach($displayExtras as $extra)

                                                @php
                                                    $extraQty = (int) (
                                                        data_get($extra, 'selected_quantity')
                                                        ?? data_get($extra, 'pivot.quantity')
                                                        ?? 1
                                                    );

                                                    $extraName = data_get($extra, 'name');
                                                @endphp

                                                <p class="text-xs leading-5 text-foreground/75">
                                                    <span class="font-semibold">
                                                        {{ $extraQty }} ×
                                                    </span>

                                                    {{ $extraName }}
                                                </p>

                                            @endforeach

                                        </div>

                                    @else

                                        <p class="mt-3 text-xs text-foreground/75">
                                            Sin adicionales
                                        </p>

                                    @endif

                                </div>

                                {{-- Subtotal --}}
                                <div class="shrink-0 text-right">

                                    <p class="text-base font-black text-primary sm:text-lg">
                                        S/ {{ number_format((float) $item['total'], 2) }}
                                    </p>

                                    <p class="mt-1 text-[11px] text-foreground/75">
                                        Subtotal
                                    </p>

                                </div>

                            </div>

                        </div>

                        {{-- ======================================
                             ACCIONES: EDITAR Y QUITAR
                        ======================================= --}}

                        <div
                            class="flex items-center justify-between gap-3 border-t border-border bg-card px-4 py-3 sm:px-5"
                        >

                            @if($canEdit)

                                <details
                                    id="edit-{{ $item['key'] }}"
                                    class="cart-editor group"
                                >
                                    <summary
                                        class="inline-flex min-h-9 cursor-pointer list-none items-center gap-2 rounded-none border border-border bg-card px-4 py-2 text-xs font-bold text-primary transition hover:bg-accent"
                                    >
                                        <span aria-hidden="true">✎</span>
                                        Editar
                                    </summary>
                                </details>

                            @else

                                <span class="text-xs text-foreground/75">
                                    Configuración del producto
                                </span>

                            @endif

                            <form
                                method="POST"
                                action="{{ route('cart.destroy', $item['key']) }}"
                                onsubmit="return confirm('¿Quitar esta configuración del carrito?')"
                            >
                                @csrf
                                @method('DELETE')

                                <button
                                    type="submit"
                                    class="inline-flex min-h-9 items-center gap-2 rounded-none px-3 py-2 text-xs font-semibold text-destructive transition hover:bg-destructive/10"
                                >
                                    <span aria-hidden="true">×</span>
                                    Quitar
                                </button>
                            </form>

                        </div>

                        {{-- ======================================
                             PANEL DE EDICIÓN
                        ======================================= --}}

                        @if($canEdit)

                            <div
                                data-editor-panel="{{ $item['key'] }}"
                                class="hidden border-t border-border bg-card px-4 py-5 sm:px-5"
                            >

                                <div class="mb-5">

                                    <h3 class="text-sm font-black text-foreground">
                                        Personalizar configuración
                                    </h3>

                                    <p class="mt-1 text-xs leading-5 text-foreground/75">
                                        Cambia los adicionales sin abandonar el carrito.
                                    </p>

                                </div>

                                <form
                                    method="POST"
                                    action="{{ route('cart.update', $item['key']) }}"
                                    class="space-y-5"
                                >
                                    @csrf
                                    @method('PATCH')

                                    <input
                                        type="hidden"
                                        name="edit_mode"
                                        value="configuration"
                                    >

                                    {{-- Editar una o todas las unidades --}}
                                    @if($quantity > 1)

                                        <fieldset
                                            class="rounded-none border border-border bg-card p-4"
                                        >
                                            <legend class="px-2 text-xs font-bold text-foreground">
                                                ¿Qué deseas editar?
                                            </legend>

                                            <div class="space-y-3">

                                                <label class="flex cursor-pointer items-center gap-3 text-sm text-foreground">
                                                    <input
                                                        type="radio"
                                                        name="edit_scope"
                                                        value="one"
                                                        checked
                                                        class="accent-primary"
                                                    >

                                                    Solo una unidad
                                                </label>

                                                <label class="flex cursor-pointer items-center gap-3 text-sm text-foreground">
                                                    <input
                                                        type="radio"
                                                        name="edit_scope"
                                                        value="all"
                                                        class="accent-primary"
                                                    >

                                                    Todas las {{ $quantity }} unidades de esta configuración
                                                </label>

                                            </div>

                                        </fieldset>

                                    @else

                                        <input
                                            type="hidden"
                                            name="edit_scope"
                                            value="all"
                                        >

                                    @endif

                                    {{-- Editor de combo --}}
                                    @if($isCombo)

                                        <div class="space-y-3">

                                            <p class="text-xs leading-5 text-foreground/75">
                                                Puedes aumentar los componentes.
                                                No se pueden reducir por debajo de la
                                                cantidad incluida en el combo.
                                            </p>

                                            @foreach($comboComponents as $component)

                                                @php
                                                    $included = (int) $component['included_quantity'];
                                                    $selected = (int) $component['selected_quantity'];
                                                @endphp

                                                <div
                                                    class="flex items-center justify-between gap-3 rounded-none border border-border bg-card p-3"
                                                >

                                                    <div class="min-w-0 flex-1">

                                                        <p class="text-xs font-bold text-foreground sm:text-sm">
                                                            {{ $component['name'] }}
                                                        </p>

                                                        <p class="mt-1 text-[11px] text-foreground/75">
                                                            Incluye {{ $included }}
                                                        </p>

                                                    </div>

                                                    <input
                                                        type="hidden"
                                                        name="components[{{ $loop->index }}][type]"
                                                        value="{{ $component['type'] }}"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="components[{{ $loop->index }}][id]"
                                                        value="{{ $component['id'] }}"
                                                    >

                                                    <div
                                                        data-counter
                                                        class="flex shrink-0 items-center gap-1 rounded-none border border-border bg-background p-1"
                                                    >

                                                        <button
                                                            type="button"
                                                            data-step="-1"
                                                            aria-label="Disminuir {{ $component['name'] }}"
                                                            class="flex h-8 w-8 items-center justify-center rounded-none bg-card text-lg font-bold disabled:opacity-30"
                                                        >
                                                            −
                                                        </button>

                                                        <output
                                                            data-counter-output
                                                            class="min-w-5 text-center text-xs font-black tabular-nums"
                                                        >
                                                            {{ $selected }}
                                                        </output>

                                                        <input
                                                            type="hidden"
                                                            data-counter-input
                                                            name="components[{{ $loop->index }}][quantity]"
                                                            value="{{ $selected }}"
                                                            min="{{ $included }}"
                                                            max="50"
                                                        >

                                                        <button
                                                            type="button"
                                                            data-step="1"
                                                            aria-label="Aumentar {{ $component['name'] }}"
                                                            class="flex h-8 w-8 items-center justify-center rounded-none bg-primary text-lg font-bold text-primary-foreground disabled:opacity-30"
                                                        >
                                                            +
                                                        </button>

                                                    </div>

                                                </div>

                                            @endforeach

                                        </div>

                                    @else

                                        {{-- Adicionales de productos y recomendaciones --}}
                                        <div class="space-y-6">

                                            @foreach($extraGroups as $categoryName => $groupExtras)

                                                <section>

                                                    <h4
                                                        class="mb-3 text-xs font-black uppercase tracking-wide text-primary"
                                                    >
                                                        {{ $categoryName }}
                                                    </h4>

                                                    <div class="space-y-2">

                                                        @foreach($groupExtras as $extra)

                                                            @php
                                                                $selectedQty =
                                                                    (int) ($selectedQuantities[$extra->id] ?? 0);
                                                            @endphp

                                                            <div
                                                                class="flex items-center gap-3 rounded-none border border-border bg-card p-3"
                                                            >

                                                                <div
                                                                    class="h-12 w-12 shrink-0 overflow-hidden rounded-none bg-accent"
                                                                >
                                                                    @if($extra->mediaAsset || $extra->image_path)

                                                                        <x-catalog-image
                                                                            :item="$extra"
                                                                            variant="thumbnail"
                                                                            loading="lazy"
                                                                            class="h-full w-full object-cover"
                                                                        />

                                                                    @else

                                                                        <div class="flex h-full items-center justify-center text-lg">
                                                                            🍴
                                                                        </div>

                                                                    @endif
                                                                </div>

                                                                <div class="min-w-0 flex-1">

                                                                    <p class="text-xs font-bold leading-5 text-foreground">
                                                                        {{ $extra->name }}
                                                                    </p>

                                                                    <p class="mt-1 text-xs font-semibold text-primary">
                                                                        + S/ {{ number_format((float) $extra->price, 2) }}
                                                                    </p>

                                                                </div>

                                                                <input
                                                                    type="hidden"
                                                                    name="extras[{{ $loop->parent->index }}_{{ $loop->index }}][id]"
                                                                    value="{{ $extra->id }}"
                                                                >

                                                                <div
                                                                    data-counter
                                                                    class="flex shrink-0 items-center gap-1 rounded-none border border-border bg-background p-1"
                                                                >

                                                                    <button
                                                                        type="button"
                                                                        data-step="-1"
                                                                        aria-label="Disminuir {{ $extra->name }}"
                                                                        class="flex h-8 w-8 items-center justify-center rounded-none bg-card text-lg font-bold disabled:opacity-30"
                                                                    >
                                                                        −
                                                                    </button>

                                                                    <output
                                                                        data-counter-output
                                                                        class="min-w-5 text-center text-xs font-black tabular-nums"
                                                                    >
                                                                        {{ $selectedQty }}
                                                                    </output>

                                                                    <input
                                                                        type="hidden"
                                                                        data-counter-input
                                                                        name="extras[{{ $loop->parent->index }}_{{ $loop->index }}][quantity]"
                                                                        value="{{ $selectedQty }}"
                                                                        min="0"
                                                                        max="50"
                                                                    >

                                                                    <button
                                                                        type="button"
                                                                        data-step="1"
                                                                        aria-label="Aumentar {{ $extra->name }}"
                                                                        class="flex h-8 w-8 items-center justify-center rounded-none bg-primary text-lg font-bold text-primary-foreground disabled:opacity-30"
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

                                    @endif

                                    <div class="flex flex-wrap gap-3 border-t border-border pt-4">

                                        <button
                                            type="submit"
                                            class="inline-flex min-h-11 items-center justify-center rounded-none bg-primary px-5 py-3 text-sm font-bold text-primary-foreground transition hover:bg-primary/90"
                                        >
                                            Guardar cambios
                                        </button>

                                        <button
                                            type="button"
                                            data-close-editor="{{ $item['key'] }}"
                                            class="inline-flex min-h-11 items-center justify-center rounded-none border border-border bg-card px-5 py-3 text-sm font-semibold text-foreground/75 hover:bg-muted"
                                        >
                                            Cancelar
                                        </button>

                                    </div>

                                </form>

                            </div>

                        @endif

                    </article>

                @endforeach

            </div>

            {{-- ==========================================
                 RESUMEN
            =========================================== --}}

            <aside class="min-w-0 lg:sticky lg:top-24">

                <div
                    class="overflow-hidden rounded-none border border-border bg-card shadow-storefront"
                >

                    <div class="border-b border-border px-5 py-5 sm:px-6">

                        <p
                            class="text-xs font-bold uppercase tracking-wide text-primary"
                        >
                            Tu pedido
                        </p>

                        <h2 class="mt-1 text-xl font-black text-foreground">
                            Resumen
                        </h2>

                    </div>

                    <div class="p-5 sm:p-6">

                        <div class="space-y-4">

                            <div class="flex items-center justify-between gap-3">

                                <span class="text-sm text-foreground/75">
                                    Productos
                                </span>

                                <span class="text-sm font-bold text-foreground">
                                    S/ {{ number_format((float) $subtotal, 2) }}
                                </span>

                            </div>

                            <div class="flex items-center justify-between gap-3">

                                <span class="text-sm text-foreground/75">
                                    Delivery
                                </span>

                                <span class="text-xs font-medium text-foreground/75">
                                    Por calcular
                                </span>

                            </div>

                        </div>

                        <div
                            class="mt-5 flex items-center justify-between gap-4 border-t border-border pt-5"
                        >

                            <span class="font-black text-foreground">
                                Subtotal
                            </span>

                            <span class="text-2xl font-black text-primary">
                                S/ {{ number_format((float) $subtotal, 2) }}
                            </span>

                        </div>

                        <p class="mt-3 text-xs leading-5 text-foreground/75">
                            El costo de delivery se calculará según tu ubicación
                            durante la confirmación del pedido.
                        </p>

                        <a
                            href="{{ route('cart.checkout') }}"
                            class="mt-6 flex min-h-12 w-full items-center justify-center rounded-none bg-primary px-5 py-3 text-center text-sm font-black text-primary-foreground transition hover:bg-primary/90"
                        >
                            Continuar con mi pedido
                        </a>

                        <a
                            href="{{ route('storefront.index') }}"
                            class="mt-4 block text-center text-xs font-semibold text-foreground/75 hover:text-primary"
                        >
                            Agregar más productos
                        </a>

                    </div>

                </div>

            </aside>

        </div>

    @endif

</section>

<script>
document.addEventListener('DOMContentLoaded', () => {
    'use strict';

    /*
     * Mostrar/ocultar el editor de cada línea.
     * Se utiliza <details> para mantener una interacción
     * accesible sin una biblioteca adicional.
     */
    document.querySelectorAll('.cart-editor').forEach(details => {
        const key = details.id.replace('edit-', '');

        const panel = document.querySelector(
            `[data-editor-panel="${key}"]`
        );

        if (!panel) {
            return;
        }

        details.addEventListener('toggle', () => {
            panel.classList.toggle('hidden', !details.open);
        });
    });

    document.querySelectorAll('[data-close-editor]').forEach(button => {
        button.addEventListener('click', () => {
            const key = button.dataset.closeEditor;
            const details = document.getElementById(`edit-${key}`);

            if (details) {
                details.open = false;
            }
        });
    });

    /*
     * Controles compactos de cantidades.
     */
    function syncCounter(counter) {
        const input = counter.querySelector('[data-counter-input]');
        const output = counter.querySelector('[data-counter-output]');

        if (!input || !output) {
            return;
        }

        const value = Number(input.value);
        const min = Number(input.min);
        const max = Number(input.max);

        output.textContent = String(value);

        counter.querySelectorAll('[data-step]').forEach(button => {
            const step = Number(button.dataset.step);

            button.disabled =
                step < 0
                    ? value <= min
                    : value >= max;
        });
    }

    document.querySelectorAll('[data-counter]').forEach(counter => {
        syncCounter(counter);
    });

    document.addEventListener('click', event => {
        const button = event.target.closest(
            '[data-counter] [data-step]'
        );

        if (!button) {
            return;
        }

        const counter = button.closest('[data-counter]');
        const input = counter.querySelector('[data-counter-input]');

        if (!input) {
            return;
        }

        const min = Number(input.min);
        const max = Number(input.max);
        const current = Number(input.value);
        const step = Number(button.dataset.step);

        const next = Math.max(
            min,
            Math.min(max, current + step)
        );

        input.value = String(next);

        syncCounter(counter);
    });
});
</script>

@endsection
