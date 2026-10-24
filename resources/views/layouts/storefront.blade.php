<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>@yield('title', 'Chifa Mi Hogar')</title>

    <meta name="description"
          content="@yield('meta-description', 'Chifa Mi Hogar: disfruta deliciosos platos de chifa con delivery en San Juan de Lurigancho, Lima.')">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-[#FAF8F5] text-[#242424] antialiased">

<header class="sticky top-0 z-40 border-b border-gray-100 bg-white/95 backdrop-blur">
    <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-4 sm:px-6">

        <a href="{{ route('storefront.index') }}"
           class="text-xl font-black tracking-tight text-[#982E26] sm:text-2xl">
            Chifa Mi Hogar
        </a>

        <div class="flex items-center gap-3">
            <span class="rounded-full bg-[#FFF4E7] px-3 py-2
                         text-xs font-semibold text-[#982E26]">
                Solo delivery
            </span>

            <a href="{{ route('cart.index') }}"
            class="rounded-xl bg-[#982E26] px-4 py-3
                    text-sm font-semibold text-white">
                🛒 Carrito
            </a>
        </div>

    </div>
</header>

<main>
    @yield('content')
</main>

<footer class="mt-16 border-t border-gray-200 bg-white">
    <div class="mx-auto max-w-7xl px-4 py-10 text-center sm:px-6">
        <p class="text-lg font-bold text-[#982E26]">
            Chifa Mi Hogar
        </p>

        <p class="mt-2 text-sm text-gray-500">
            Preparado con sabor, entregado con cariño.
        </p>

        <p class="mt-5 text-xs text-gray-400">
            © {{ date('Y') }} Chifa Mi Hogar.
        </p>
    </div>
</footer>

</body>
</html>