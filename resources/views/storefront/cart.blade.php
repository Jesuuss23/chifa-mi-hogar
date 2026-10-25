@extends('layouts.storefront')

@section('title', 'Mi carrito | Chifa Mi Hogar')

@section('content')

<section class="mx-auto max-w-5xl px-4 py-12 sm:px-6">

    <div class="mb-8 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-3xl font-black">Mi carrito</h1>
            <p class="mt-2 text-gray-500">
                Revisa tus platos antes de continuar.
            </p>
        </div>

        <a href="{{ route('storefront.index') }}"
           class="text-sm font-semibold text-[#982E26]">
            ← Seguir comprando
        </a>
    </div>

    @if(session('success'))
        <div role="status"
             class="mb-6 rounded-lg bg-green-50 p-4 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div role="alert"
             class="mb-6 rounded-lg bg-red-50 p-4 text-red-700">
            {{ $errors->first() }}
        </div>
    @endif

    @if(count($cart) === 0)
        <div class="rounded-2xl bg-white p-12 text-center shadow-sm">
            <h2 class="text-xl font-bold">Tu carrito está vacío</h2>
            <p class="mt-3 text-gray-500">
                Explora nuestra carta y agrega tus platos favoritos.
            </p>

            <a href="{{ route('storefront.index') }}"
               class="mt-6 inline-block rounded-xl bg-[#982E26]
                      px-6 py-3 font-bold text-white">
                Ver carta
            </a>
        </div>
    @else

        <div class="grid gap-6 lg:grid-cols-3">

            <div class="space-y-4 lg:col-span-2">
                @foreach($cart as $item)
                    <article class="rounded-2xl bg-white p-5 shadow-sm">
                        <div class="flex gap-4">

                        @if($item['type'] === 'combo')

                            @if($item['combo']->mediaAsset)
                                <img
                                    src="{{ $item['combo']->mediaAsset->cardUrl() }}"
                                    alt="{{ $item['combo']->name }}"
                                    class="h-28 w-32 rounded-xl object-cover"
                                >
                            @endif

                        @else

                            @if($item['product']->media_asset_id || $item['product']->image_path)
                                <x-catalog-image
                                    :item="$item['product']"
                                    variant="card"
                                    class="h-28 w-32 rounded-xl object-cover"
                                />
                            @endif

                        @endif

                            <div class="min-w-0 flex-1">
                            <h2 class="font-bold">
                                @if($item['type'] === 'combo')
                                    {{ $item['combo']->name }}
                                    <span class="ml-2 rounded bg-amber-100 px-2 py-1 text-xs text-amber-800">
                                        Combo
                                    </span>
                                @else
                                    {{ $item['product']->name }}
                                @endif
                            </h2>

                            @if($item['type'] === 'combo')

                                <div class="mt-3 space-y-1">

                                    @foreach($item['components'] as $component)

                                        <p class="text-xs text-gray-600">
                                            {{ $component['selected_quantity'] }} ×
                                            {{ $component['name'] }}

                                            @if($component['additional_quantity'] > 0)
                                                <span class="font-semibold text-[#982E26]">
                                                    (+{{ $component['additional_quantity'] }} adicional)
                                                </span>
                                            @endif
                                        </p>

                                    @endforeach

                                </div>

                            @else

                                @foreach($item['extras'] as $extra)
                                    <p class="mt-1 text-xs text-gray-500">
                                        + {{ $extra->name }}
                                    </p>
                                @endforeach

                            @endif

                                <p class="mt-3 font-bold text-[#982E26]">
                                    S/ {{ number_format($item['unit_price'], 2) }}
                                    <span class="text-xs font-normal text-gray-500">
                                        por unidad
                                    </span>
                                </p>
                            </div>
                        </div>

                        <div class="mt-5 flex flex-wrap items-center
                                    justify-between gap-4 border-t pt-4">

                            <form method="POST"
                                  action="{{ route('cart.update', $item['key']) }}"
                                  class="flex items-center gap-2">
                                @csrf
                                @method('PATCH')

                                <label for="qty-{{ $item['key'] }}"
                                       class="text-sm">
                                    Cantidad
                                </label>

                                <input id="qty-{{ $item['key'] }}"
                                       type="number" name="quantity"
                                       min="1" max="20"
                                       value="{{ $item['quantity'] }}"
                                       class="w-20 rounded-lg border p-2">

                                <button type="submit"
                                        class="text-sm font-semibold text-blue-700">
                                    Actualizar
                                </button>
                            </form>

                            <form method="POST"
                                  action="{{ route('cart.destroy', $item['key']) }}">
                                @csrf
                                @method('DELETE')

                                <button type="submit"
                                        class="text-sm font-semibold text-red-700">
                                    Quitar
                                </button>
                            </form>
                        </div>

                        <p class="mt-4 text-right font-bold">
                            Subtotal:
                            S/ {{ number_format($item['total'], 2) }}
                        </p>
                    </article>
                @endforeach
            </div>

            <aside class="h-fit rounded-2xl bg-white p-6 shadow-sm">
                <h2 class="text-xl font-bold">Resumen del pedido</h2>

                <div class="mt-6 flex justify-between">
                    <span class="text-gray-500">Productos</span>
                    <span class="font-semibold">
                        S/ {{ number_format($subtotal, 2) }}
                    </span>
                </div>

                <div class="mt-4 flex justify-between gap-3">
                    <span class="text-gray-500">Delivery</span>
                    <span class="text-sm">Por calcular</span>
                </div>

                <div class="mt-6 flex justify-between border-t pt-5">
                    <span class="font-bold">Subtotal</span>
                    <span class="text-xl font-black text-[#982E26]">
                        S/ {{ number_format($subtotal, 2) }}
                    </span>
                </div>

                <p class="mt-4 text-xs leading-5 text-gray-500">
                    El costo de delivery se confirmará según
                    la dirección de entrega.
                </p>

                <a href="{{ route('cart.checkout') }}"
                class="mt-6 block rounded-xl bg-[#982E26] px-6 py-4
                        text-center font-bold text-white">
                    Continuar con mi pedido
                </a>
            </aside>

        </div>
    @endif

</section>

@endsection