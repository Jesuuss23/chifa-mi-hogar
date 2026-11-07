@extends('layouts.storefront')

@section('title', 'Carta digital | Chifa Mi Hogar')

@section('content')

<div class="storefront-home">
@php
    $heroProduct = $products->first(fn ($product) => $product->mediaAsset || $product->image_path);
    $heroImage = $heroProduct ? app(\App\Services\CatalogImageService::class)->url($heroProduct, 'card') : null;
@endphp
<section class="catalog-surface home-hero" aria-labelledby="home-title">
    @if($storefrontTheme === 'christmas')
        @include('storefront.partials.christmas-decor')
    @endif
    <div class="home-hero__inner {{ $heroImage ? 'home-hero__inner--with-image' : '' }}">
        <div>
            <p class="home-eyebrow">CHIFA MI HOGAR · SOLO DELIVERY</p>
            <h1 id="home-title">El sabor que<br>te hace volver.</h1>
            <p class="home-hero__description">Tu plato favorito, preparado al momento y a tu gusto. Elige, personaliza y disfruta en casa.</p>
            <a href="#carta" class="home-hero__cta">Explorar carta <span aria-hidden="true">↓</span></a>
            <p class="home-hero__note">Platos y combos · Adicionales a tu elección</p>
        </div>
        @if($heroImage)
            <a href="{{ route('storefront.show', $heroProduct->slug) }}" class="home-hero__feature" aria-label="Ver plato: {{ $heroProduct->name }}">
                <img src="{{ $heroImage }}" alt="" width="640" height="480" fetchpriority="high" decoding="async">
                <span class="home-hero__caption">
                    <span><span class="home-eyebrow">DE NUESTRA CARTA</span><strong>{{ $heroProduct->name }}</strong></span>
                    <span class="home-hero__price">S/ {{ number_format((float) $heroProduct->price, 2) }}</span>
                </span>
            </a>
        @endif
    </div>
</section>

@include('storefront.partials.combo-carousel')

<!-- CARTA -->
<section id="carta" class="home-menu catalog-surface mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:py-16">

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
         class="home-categories mb-9 flex gap-3 overflow-x-auto pb-3">

        <a href="{{ route('storefront.index') }}#carta"
           @if(!$selectedCategory) aria-current="page" @endif
           class="shrink-0 rounded-none px-6 py-3 text-sm font-bold
                  {{ !$selectedCategory
                      ? 'bg-primary text-primary-foreground'
                      : 'bg-card text-foreground shadow-storefront' }}">
            Todos
        </a>

        @if($combos->isNotEmpty())
            <a href="#combos" class="home-combos-link shrink-0 rounded-none border border-border bg-card px-6 py-3 text-sm font-bold text-primary"
               aria-label="Combos: ir al carrusel">Combos <span aria-hidden="true">↗</span></a>
        @endif

        @foreach($categories as $category)
            <a href="{{ route('storefront.index', ['categoria' => $category->slug]) }}#carta"
               @if($selectedCategory?->id === $category->id) aria-current="page" @endif
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
            <article class="home-product group flex h-full flex-col overflow-hidden
                            rounded-none border border-border bg-card
                            shadow-storefront transition duration-300
                            hover:shadow-storefront">

                <a href="{{ route('storefront.show', $product->slug) }}"
                   class="home-product__image relative block overflow-hidden">

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

</div>
@endsection