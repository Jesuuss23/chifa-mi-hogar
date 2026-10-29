@extends('layouts.storefront')

@section('title', 'Carta digital | Chifa Mi Hogar')

@section('content')

<!-- HERO -->
<section class="catalog-surface relative isolate overflow-hidden bg-muted text-foreground">

    <div class="absolute inset-0 bg-gradient-to-r
                from-muted via-muted/95 to-background/70">
    </div>

    <div class="relative mx-auto flex min-h-[340px] max-w-7xl
                flex-col justify-center px-4 py-16 sm:px-6 lg:py-24">

        <p class="mb-4 text-xs font-bold uppercase tracking-[0.3em]
                  text-primary">
            Bienvenido a Chifa Mi Hogar
        </p>

        <h1 class="max-w-2xl text-4xl font-black leading-tight
                   sm:text-5xl lg:text-6xl">
            El sabor que te hace volver
        </h1>

        <p class="mt-5 max-w-lg text-base leading-7 text-foreground/75">
            Disfruta nuestros platos preparados al momento.
            Elige tus favoritos y recíbelos por delivery.
        </p>

        <div class="mt-8">
            <a href="#carta"
               class="inline-flex items-center justify-center
                      rounded-none bg-primary px-7 py-3
                      font-bold text-primary-foreground transition
                      hover:bg-primary/90">
                Explorar carta ↓
            </a>
        </div>
    </div>
</section>

@include('storefront.partials.combo-carousel')

<!-- CARTA -->
<section id="carta" class="catalog-surface mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:py-16">

    <div class="mb-8">
        <p class="mb-2 text-xs font-bold uppercase
                  tracking-[0.2em] text-primary">
            Nuestros platos
        </p>

        <h2 class="text-3xl font-black sm:text-4xl">
            Nuestra carta
        </h2>

        <p class="mt-3 text-foreground/75">
            Descubre el sabor de cada especialidad.
        </p>
    </div>

    <!-- CATEGORÍAS -->
    <nav aria-label="Categorías"
         class="mb-9 flex gap-3 overflow-x-auto pb-3">

        <a href="{{ route('storefront.index') }}#carta"
           class="shrink-0 rounded-none px-6 py-3 text-sm font-bold
                  {{ !$selectedCategory
                      ? 'bg-primary text-primary-foreground'
                      : 'bg-card text-foreground shadow-storefront' }}">
            Todos
        </a>

        @foreach($categories as $category)
            <a href="{{ route('storefront.index', ['categoria' => $category->slug]) }}#carta"
               class="shrink-0 rounded-none px-6 py-3 text-sm font-bold
                      {{ $selectedCategory?->id === $category->id
                          ? 'bg-primary text-primary-foreground'
                          : 'bg-card text-foreground shadow-storefront' }}">
                {{ $category->name }}
            </a>
        @endforeach
    </nav>

    <!-- PRODUCTOS -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">

        @forelse($products as $product)
            <article class="group flex h-full flex-col overflow-hidden
                            rounded-none border border-border bg-card
                            shadow-storefront transition duration-300
                            hover:-translate-y-1 hover:shadow-storefront">

                <a href="{{ route('storefront.show', $product->slug) }}"
                   class="relative block overflow-hidden">

                    @if($product->mediaAsset || $product->image_path)
                        <x-catalog-image
                            :item="$product"
                            variant="card"
                            class="h-full w-full object-cover"
                        />
                    @else
                        <div class="flex h-full w-full items-center justify-center bg-accent text-foreground/75">
                            Fotografía próximamente
                        </div>
                    @endif

                    @if($product->is_featured)
                        <span class="absolute left-3 top-3 rounded-none
                                     bg-accent px-3 py-1 text-xs
                                     font-bold text-accent-foreground">
                            ★ Destacado
                        </span>
                    @endif
                </a>

                <div class="flex flex-1 flex-col p-5">

                    <p class="text-xs font-bold uppercase
                              tracking-wide text-primary">
                        {{ $product->category->name }}
                    </p>

                    <h3 class="mt-2 text-lg font-bold">
                        {{ $product->name }}
                    </h3>

                    <p class="mt-2 line-clamp-2 text-sm
                              leading-6 text-foreground/75">
                        {{ $product->description ?: 'Preparado con nuestro sabor especial.' }}
                    </p>

                    <div class="mt-auto flex items-center
                                justify-between gap-3 pt-6">

                        <span class="text-xl font-black text-primary">
                            S/ {{ number_format((float)$product->price, 2) }}
                        </span>

                        <a href="{{ route('storefront.show', $product->slug) }}"
                           class="rounded-none bg-primary px-4 py-3
                                  text-sm font-bold text-primary-foreground
                                  transition hover:bg-primary/90">
                            Ver plato
                        </a>
                    </div>
                </div>
            </article>

        @empty
            <div class="col-span-full rounded-none bg-card p-12 text-center">
                <h3 class="text-xl font-bold">
                    No hay platos disponibles
                </h3>

                <p class="mt-3 text-foreground/75">
                    Pronto encontrarás más especialidades.
                </p>
            </div>
        @endforelse
    </div>

    <div class="mt-10">
        {{ $products->links('pagination.storefront') }}
    </div>

</section>

@endsection