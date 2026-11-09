@extends('layouts.storefront')
@section('title', 'Combos y promociones | Chifa Mi Hogar')
@section('content')
<section class="mx-auto max-w-7xl px-4 py-8">

    <a href="{{ route('storefront.index') }}"
       class="text-sm font-semibold text-primary">
        ← Volver a la carta
    </a>

    <h1 class="mt-5 text-3xl font-bold">Combos y promociones</h1>



    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

        @php($firstOfferId = $combos->first(fn ($entry) => $entry->reference_price > (float) $entry->price)?->id)
        @forelse ($combos as $combo)

            <article class="promo-card overflow-hidden rounded-none bg-card shadow-storefront">
                <x-combo-offer :combo="$combo" :animate="$combo->id === $firstOfferId" />

                @if ($combo->mediaAsset)
                    <img
                        src="{{ $combo->mediaAsset->cardUrl() }}"
                        alt="{{ $combo->name }}"
                        class="h-52 w-full object-cover"
                    >
                @endif

                <div class="p-5">

                    <h2 class="text-xl font-bold">
                        {{ $combo->name }}
                    </h2>

                    <p class="mt-2 text-sm text-foreground/75">
                        {{ $combo->description }}
                    </p>

                    <ul class="mt-4 space-y-1 text-sm text-foreground/75">
                        @foreach ($combo->items as $item)
                            <li>
                                {{ $item->quantity }} ×
                                {{ $item->product?->name ?? $item->extra?->name }}
                            </li>
                        @endforeach
                    </ul>

                    <x-combo-price :combo="$combo" />

                    <a
                        href="{{ route('combos.show', $combo) }}"
                        class="mt-4 block rounded-none bg-primary px-4 py-3 text-center font-semibold text-primary-foreground"
                    >
                        Ver combo
                    </a>

                </div>

            </article>

        @empty

            <p class="text-foreground/75">
                Todavía no hay combos publicados.
            </p>

        @endforelse

    </div>

    <div class="mt-8">
        {{ $combos->links('pagination.storefront') }}
    </div>

</section>

@endsection
