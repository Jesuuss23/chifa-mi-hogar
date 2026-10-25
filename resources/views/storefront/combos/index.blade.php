<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Combos | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-50">

<main class="mx-auto max-w-7xl px-4 py-8">

    <a href="{{ route('storefront.index') }}"
       class="text-sm font-semibold text-red-700">
        ← Volver a la carta
    </a>

    <h1 class="mt-5 text-3xl font-bold">Nuestros combos</h1>

    <p class="mt-2 text-slate-600">
        Disfruta nuestros combos y personalízalos a tu gusto.
    </p>

    <div class="mt-8 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">

        @forelse ($combos as $combo)

            <article class="overflow-hidden rounded-2xl bg-white shadow-sm">

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

                    <p class="mt-2 text-sm text-slate-600">
                        {{ $combo->description }}
                    </p>

                    <ul class="mt-4 space-y-1 text-sm text-slate-500">
                        @foreach ($combo->items as $item)
                            <li>
                                {{ $item->quantity }} ×
                                {{ $item->product?->name ?? $item->extra?->name }}
                            </li>
                        @endforeach
                    </ul>

                    <p class="mt-4 text-2xl font-bold text-red-700">
                        S/ {{ number_format((float) $combo->price, 2) }}
                    </p>

                    <a
                        href="{{ route('combos.show', $combo) }}"
                        class="mt-4 block rounded-xl bg-red-700 px-4 py-3 text-center font-semibold text-white"
                    >
                        Ver combo
                    </a>

                </div>

            </article>

        @empty

            <p class="text-slate-500">
                Todavía no hay combos publicados.
            </p>

        @endforelse

    </div>

    <div class="mt-8">
        {{ $combos->links() }}
    </div>

</main>

</body>
</html>
