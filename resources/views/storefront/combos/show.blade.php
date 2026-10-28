@extends('layouts.storefront')

@section('title', $combo->name . ' | Chifa Mi Hogar')

@section('content')

<section class="catalog-surface mx-auto max-w-6xl px-4 py-10 sm:px-6">

    <a href="{{ route('combos.index') }}"
       class="mb-6 inline-block text-sm font-medium text-primary">
        ← Volver a combos
    </a>

    <div class="grid gap-10 lg:grid-cols-2">

        <div>
            @if($combo->mediaAsset)
                <img
                    src="{{ $combo->mediaAsset->detailUrl() }}"
                    alt="{{ $combo->name }}"
                    class="w-full rounded-none object-cover"
                >
            @else
                <div class="flex aspect-square items-center justify-center
                            rounded-none bg-accent text-foreground/75">
                    Fotografía próximamente
                </div>
            @endif
        </div>

        <div>
            <span class="text-sm font-semibold text-primary">
                Combos
            </span>

            <h1 class="mt-3 text-3xl font-black sm:text-4xl">
                {{ $combo->name }}
            </h1>

            <p class="mt-5 leading-7 text-foreground/75">
                {{ $combo->description }}
            </p>

            <p class="mt-6 text-3xl font-black text-primary">
                S/ {{ number_format((float) $combo->price, 2) }}
            </p>

            @if($errors->any())
                <div class="mt-6 rounded-none bg-destructive/10 p-4 text-destructive">
                    {{ $errors->first() }}
                </div>
            @endif

            <form
                method="POST"
                action="{{ route('cart.combos.store') }}"
                class="mt-8 space-y-6"
                id="combo-form"
            >
                @csrf

                <input
                    type="hidden"
                    name="combo_id"
                    value="{{ $combo->id }}"
                >

                <div>
                    <h2 class="text-xl font-bold">
                        Personaliza tu combo
                    </h2>

                    <p class="mt-2 text-sm text-foreground/75">
                        Puedes aumentar las cantidades incluidas.
                        Cada unidad adicional se cobra por separado.
                    </p>
                </div>

                <div class="space-y-4">

                    @foreach($combo->items as $index => $item)

                        @php
                            $catalogItem = $item->product ?: $item->extra;
                            $type = $item->product_id ? 'product' : 'extra';
                            $included = (int) $item->quantity;
                            $price = (float) $catalogItem->price;
                        @endphp

                        <div class="rounded-none border bg-card p-4">

                            <div class="flex flex-wrap items-center justify-between gap-4">

                                <div>
                                    <h3 class="font-bold">
                                        {{ $catalogItem->name }}
                                    </h3>

                                    <p class="mt-1 text-xs text-foreground/75">
                                        Incluye {{ $included }}
                                        · Adicional S/ {{ number_format($price, 2) }}
                                    </p>
                                </div>

                                <input
                                    type="number"
                                    name="components[{{ $index }}][quantity]"
                                    value="{{ old('components.'.$index.'.quantity', $included) }}"
                                    min="{{ $included }}"
                                    max="50"
                                    required
                                    data-combo-component
                                    data-included="{{ $included }}"
                                    data-price-cents="{{ (int) round($price * 100) }}"
                                    class="w-24 rounded-none border p-3"
                                >

                            </div>

                            <input
                                type="hidden"
                                name="components[{{ $index }}][type]"
                                value="{{ $type }}"
                            >

                            <input
                                type="hidden"
                                name="components[{{ $index }}][id]"
                                value="{{ $catalogItem->id }}"
                            >

                        </div>

                    @endforeach

                </div>

                <div>
                    <label for="combo-quantity" class="mb-2 block font-semibold">
                        Cantidad de combos
                    </label>

                    <input
                        id="combo-quantity"
                        type="number"
                        name="quantity"
                        value="{{ old('quantity', 1) }}"
                        min="1"
                        max="20"
                        required
                        class="w-28 rounded-none border p-3"
                    >
                </div>

                <div class="rounded-none bg-accent p-5">

                    <p class="text-sm text-foreground/75">
                        Total estimado
                    </p>

                    <p
                        id="combo-total"
                        class="mt-2 text-3xl font-black text-primary"
                    >
                        S/ {{ number_format((float) $combo->price, 2) }}
                    </p>

                    <p class="mt-2 text-xs text-foreground/75">
                        El precio definitivo se verifica al procesar el pedido.
                    </p>

                </div>

                <button
                    type="submit"
                    class="w-full rounded-none bg-primary px-6 py-4
                           font-bold text-primary-foreground hover:bg-primary/90"
                >
                    Agregar combo al carrito
                </button>

            </form>

        </div>

    </div>

</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('combo-form');
    const quantityInput = document.getElementById('combo-quantity');
    const totalElement = document.getElementById('combo-total');
    const components = form.querySelectorAll('[data-combo-component]');

    const baseCents = @json((int) round((float) $combo->price * 100));

    function calculateTotal() {
        let unitCents = baseCents;

        components.forEach(function (input) {
            const included = Number(input.dataset.included);
            const priceCents = Number(input.dataset.priceCents);
            const selected = Number(input.value);

            const additional = Math.max(0, selected - included);

            unitCents += additional * priceCents;
        });

        const quantity = Math.max(1, Number(quantityInput.value) || 1);
        const totalCents = unitCents * quantity;

        totalElement.textContent =
            'S/ ' + (totalCents / 100).toFixed(2);
    }

    components.forEach(function (input) {
        input.addEventListener('input', calculateTotal);
    });

    quantityInput.addEventListener('input', calculateTotal);

    calculateTotal();
});
</script>

@endsection