@extends('layouts.storefront')

@section('title', $product->name . ' | Chifa Mi Hogar')

@section('content')

<section class="mx-auto max-w-6xl px-4 py-10 sm:px-6">

    <a href="{{ route('storefront.index') }}"
       class="mb-6 inline-block text-sm font-medium text-[#982E26]">
        ← Volver a la carta
    </a>

    <div class="grid gap-10 lg:grid-cols-2">

        <div>
            @if($product->mediaAsset || $product->image_path)
                <x-catalog-image
                    :item="$product"
                    variant="detail"
                    loading="eager"
                    class="w-full aspect-[4/3] object-cover rounded-2xl"
                />
            @else
                <div class="flex aspect-square items-center justify-center
                            rounded-2xl bg-[#F2E8DD] text-gray-500">
                    Fotografía próximamente
                </div>
            @endif
        </div>

        <div>
            <span class="text-sm font-semibold text-[#982E26]">
                {{ $product->category->name }}
            </span>

            <h1 class="mt-3 text-3xl font-black sm:text-4xl">
                {{ $product->name }}
            </h1>

            <p class="mt-5 leading-7 text-gray-600">
                {{ $product->description ?: 'Preparado con nuestro sabor especial.' }}
            </p>

            <p class="mt-6 text-3xl font-black text-[#982E26]">
                S/ {{ number_format((float)$product->price, 2) }}
            </p>

            @if($product->extras->isNotEmpty())
                <div class="mt-8">
                    <h2 class="text-xl font-bold">
                        Adicionales disponibles
                    </h2>

                    <p class="mt-1 text-sm text-gray-500">
                        Personaliza tu plato con nuestros complementos.
                    </p>

                    <div class="mt-4 space-y-3">
                        @foreach($product->extras as $extra)
                            <div class="flex items-center justify-between
                                        rounded-xl border border-gray-200
                                        bg-white p-4">
                                <span class="font-medium">
                                    {{ $extra->name }}
                                </span>

                                <span class="font-semibold text-[#982E26]">
                                    + S/ {{ number_format((float)$extra->price, 2) }}
                                </span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <form method="POST"
                action="{{ route('cart.store') }}"
                class="mt-8 space-y-6">

                @csrf

                <input type="hidden" name="product_id" value="{{ $product->id }}">

                @if($errors->any())
                    <div role="alert" class="rounded-lg bg-red-50 p-4 text-sm text-red-700">
                        {{ $errors->first() }}
                    </div>
                @endif

                @if($product->extras->isNotEmpty())
                    <fieldset>
                        <legend class="text-xl font-bold">
                            Personaliza tu plato
                        </legend>

                        <p class="mt-1 text-sm text-gray-500">
                            Selecciona los adicionales que deseas.
                        </p>

                        <div class="mt-4 space-y-3">
                            @foreach($product->extras as $extra)
                                <label class="flex cursor-pointer items-center
                                            justify-between gap-3 rounded-xl
                                            border border-gray-200 bg-white p-4">

                                    <span class="flex items-center gap-3">
                                        <input type="checkbox"
                                            name="extras[]"
                                            value="{{ $extra->id }}"
                                            @checked(in_array($extra->id, old('extras', [])))
                                            class="h-5 w-5 accent-[#982E26]">

                                        <span class="font-medium">
                                            {{ $extra->name }}
                                        </span>
                                    </span>

                                    <span class="font-semibold text-[#982E26]">
                                        + S/ {{ number_format((float) $extra->price, 2) }}
                                    </span>
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                @endif

                <div>
                    <label for="quantity" class="mb-2 block font-semibold">
                        Cantidad
                    </label>

                    <input id="quantity" type="number" name="quantity"
                        value="{{ old('quantity', 1) }}"
                        min="1" max="20" required
                        class="w-28 rounded-lg border border-gray-300 p-3">
                </div>

                <button type="submit"
                        class="w-full rounded-xl bg-[#982E26] px-6 py-4
                            font-bold text-white hover:bg-[#B5382E]">
                    Agregar al carrito
                </button>
            </form>
        </div>

    </div>
</section>

@endsection