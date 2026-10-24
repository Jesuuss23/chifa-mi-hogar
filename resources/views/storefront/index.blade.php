@extends('layouts.storefront')

@section('title', 'Carta digital | Chifa Mi Hogar')

@section('content')

<!-- HERO -->
<section class="relative isolate overflow-hidden bg-[#171717] text-white">

    <div class="absolute inset-0 bg-gradient-to-r
                from-[#171717] via-[#171717]/95 to-[#171717]/70">
    </div>

    <div class="relative mx-auto flex min-h-[340px] max-w-7xl
                flex-col justify-center px-4 py-16 sm:px-6 lg:py-24">

        <p class="mb-4 text-xs font-bold uppercase tracking-[0.3em]
                  text-[#F5D28C]">
            Bienvenido a Chifa Mi Hogar
        </p>

        <h1 class="max-w-2xl text-4xl font-black leading-tight
                   sm:text-5xl lg:text-6xl">
            El sabor que te hace volver
        </h1>

        <p class="mt-5 max-w-lg text-base leading-7 text-gray-300">
            Disfruta nuestros platos preparados al momento.
            Elige tus favoritos y recíbelos por delivery.
        </p>

        <div class="mt-8">
            <a href="#carta"
               class="inline-flex items-center justify-center
                      rounded-xl bg-[#982E26] px-7 py-3
                      font-bold text-white transition
                      hover:bg-[#B5382E]">
                Explorar carta ↓
            </a>
        </div>
    </div>
</section>

<!-- CARTA -->
<section id="carta" class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:py-16">

    <div class="mb-8">
        <p class="mb-2 text-xs font-bold uppercase
                  tracking-[0.2em] text-[#982E26]">
            Nuestros platos
        </p>

        <h2 class="text-3xl font-black sm:text-4xl">
            Nuestra carta
        </h2>

        <p class="mt-3 text-gray-500">
            Descubre el sabor de cada especialidad.
        </p>
    </div>

    <!-- CATEGORÍAS -->
    <nav aria-label="Categorías"
         class="mb-9 flex gap-3 overflow-x-auto pb-3">

        <a href="{{ route('storefront.index') }}#carta"
           class="shrink-0 rounded-full px-6 py-3 text-sm font-bold
                  {{ !$selectedCategory
                      ? 'bg-[#982E26] text-white'
                      : 'bg-white text-gray-700 shadow-sm' }}">
            Todos
        </a>

        @foreach($categories as $category)
            <a href="{{ route('storefront.index', ['categoria' => $category->slug]) }}#carta"
               class="shrink-0 rounded-full px-6 py-3 text-sm font-bold
                      {{ $selectedCategory?->id === $category->id
                          ? 'bg-[#982E26] text-white'
                          : 'bg-white text-gray-700 shadow-sm' }}">
                {{ $category->name }}
            </a>
        @endforeach
    </nav>

    <!-- PRODUCTOS -->
    <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">

        @forelse($products as $product)
            <article class="group flex h-full flex-col overflow-hidden
                            rounded-2xl border border-gray-100 bg-white
                            shadow-sm transition duration-300
                            hover:-translate-y-1 hover:shadow-xl">

                <a href="{{ route('storefront.show', $product->slug) }}"
                   class="relative block overflow-hidden">

                    @if($product->image_path)
                    <x-catalog-image
                        :item="$product"
                        variant="detail"
                        loading="eager"
                        class="w-full aspect-[4/3] object-cover rounded-2xl"
                    />
                    @else
                        <div class="flex aspect-[4/3] items-center
                                    justify-center bg-[#F2E8DD]
                                    text-center text-sm text-gray-500">
                            Fotografía próximamente
                        </div>
                    @endif

                    @if($product->is_featured)
                        <span class="absolute left-3 top-3 rounded-full
                                     bg-[#F5D28C] px-3 py-1 text-xs
                                     font-bold text-[#4B3012]">
                            ★ Destacado
                        </span>
                    @endif
                </a>

                <div class="flex flex-1 flex-col p-5">

                    <p class="text-xs font-bold uppercase
                              tracking-wide text-[#982E26]">
                        {{ $product->category->name }}
                    </p>

                    <h3 class="mt-2 text-lg font-bold">
                        {{ $product->name }}
                    </h3>

                    <p class="mt-2 line-clamp-2 text-sm
                              leading-6 text-gray-500">
                        {{ $product->description ?: 'Preparado con nuestro sabor especial.' }}
                    </p>

                    <div class="mt-auto flex items-center
                                justify-between gap-3 pt-6">

                        <span class="text-xl font-black text-[#982E26]">
                            S/ {{ number_format((float)$product->price, 2) }}
                        </span>

                        <a href="{{ route('storefront.show', $product->slug) }}"
                           class="rounded-xl bg-[#982E26] px-4 py-3
                                  text-sm font-bold text-white
                                  transition hover:bg-[#B5382E]">
                            Ver plato
                        </a>
                    </div>
                </div>
            </article>

        @empty
            <div class="col-span-full rounded-2xl bg-white p-12 text-center">
                <h3 class="text-xl font-bold">
                    No hay platos disponibles
                </h3>

                <p class="mt-3 text-gray-500">
                    Pronto encontrarás más especialidades.
                </p>
            </div>
        @endforelse
    </div>

    <div class="mt-10">
        {{ $products->links() }}
    </div>

</section>

@endsection