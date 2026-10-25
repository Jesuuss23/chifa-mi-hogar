<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1">

    <meta name="robots" content="noindex, nofollow">

    <title>@yield('title', 'Administración') | Chifa Mi Hogar</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-gray-100 text-gray-900">

<div class="min-h-screen lg:flex">

    {{-- MENÚ ADMINISTRATIVO --}}
    <aside class="bg-[#171717] text-white lg:w-64 lg:shrink-0">

        {{-- Logo / Nombre --}}
        <div class="border-b border-white/10 p-6">
            <h1 class="text-xl font-bold text-[#F5D28C]">
                Chifa Mi Hogar
            </h1>

            <p class="mt-1 text-xs text-gray-400">
                Panel administrativo
            </p>
        </div>

        {{-- Navegación --}}
        <nav aria-label="Menú administrativo"
             class="flex gap-1 overflow-x-auto p-3
                    lg:flex-col lg:overflow-visible">

            {{-- DASHBOARD --}}
            <a href="{{ route('admin.dashboard') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.dashboard')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Dashboard
            </a>

            {{-- GESTIÓN DE PEDIDOS --}}
            <div class="hidden px-4 pb-1 pt-5 text-xs
                        font-bold uppercase tracking-wider
                        text-gray-500 lg:block">
                Ventas
            </div>

            <a href="{{ route('admin.orders.index') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.orders.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Pedidos
            </a>

            {{-- GESTIÓN DEL CATÁLOGO --}}
            <div class="hidden px-4 pb-1 pt-5 text-xs
                        font-bold uppercase tracking-wider
                        text-gray-500 lg:block">
                Catálogo
            </div>

            {{-- Productos --}}
            <a href="{{ route('admin.products.index') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.products.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Productos
            </a>

            {{-- Combos --}}
            <a href="{{ route('admin.combos.index') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.combos.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Combos
            </a>

            {{-- Categorías --}}
            <a href="{{ route('admin.categories.index') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.categories.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Categorías
            </a>

            {{-- Adicionales --}}
            <a href="{{ route('admin.extras.index') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.extras.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Adicionales
            </a>

            {{-- Categorías de adicionales --}}
            <a href="{{ route('admin.extra-categories.index') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.extra-categories.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Categorías de adicionales
            </a>

            {{-- CONFIGURACIÓN --}}
            <div class="hidden px-4 pb-1 pt-5 text-xs
                        font-bold uppercase tracking-wider
                        text-gray-500 lg:block">
                Configuración
            </div>

            {{-- Configuración general --}}
            <a href="{{ route('admin.settings.edit') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.settings.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Configuración general
            </a>

            {{-- WhatsApp --}}
            <a href="{{ route('admin.whatsapp.index') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.whatsapp.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                WhatsApp Meta
            </a>

            {{-- Redes sociales --}}
            <a href="{{ route('admin.social-links.index') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.social-links.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Redes sociales
            </a>

            {{-- RESEÑAS --}}
            <div class="hidden px-4 pb-1 pt-5 text-xs
                        font-bold uppercase tracking-wider
                        text-gray-500 lg:block">
                Reseñas
            </div>

            {{-- Reseñas de Google --}}
            <a href="{{ route('admin.reviews.google') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.reviews.google*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Reseñas de Google
            </a>

            {{-- Valoraciones de pedidos --}}
            <a href="{{ route('admin.order-ratings.index') }}"
               class="rounded-lg px-4 py-3 text-sm transition
                      hover:bg-white/10
                      {{ request()->routeIs('admin.order-ratings.*')
                          ? 'bg-[#982E26] text-white'
                          : '' }}">
                Valoraciones de pedidos
            </a>

            {{-- ACCESO A LA TIENDA --}}
            <div class="mt-4 border-t border-white/10 pt-4">

                <a href="{{ route('storefront.index') }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="block rounded-lg px-4 py-3 text-sm
                          font-semibold text-[#F5D28C]
                          transition hover:bg-white/10">
                    Ver tienda pública ↗
                </a>

                <a href="{{ route('combos.index') }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="block rounded-lg px-4 py-3 text-sm
                          text-gray-300 transition
                          hover:bg-white/10">
                    Ver combos publicados ↗
                </a>

            </div>

        </nav>
    </aside>

    {{-- CONTENIDO PRINCIPAL --}}
    <div class="min-w-0 flex-1">

        {{-- HEADER --}}
        <header class="flex items-center justify-between
                       gap-4 border-b bg-white px-6 py-4">

            <h2 class="font-semibold">
                @yield('page-title', 'Administración')
            </h2>

            <div class="flex items-center gap-4">

                {{-- Usuario autenticado --}}
                <span class="hidden text-sm text-gray-600 sm:block">
                    {{ auth()->user()->name }}
                </span>

                {{-- Cerrar sesión --}}
                <form method="POST"
                      action="{{ route('admin.logout') }}">

                    @csrf

                    <button type="submit"
                            class="rounded-lg bg-[#982E26]
                                   px-4 py-2 text-sm font-semibold
                                   text-white transition
                                   hover:bg-[#B5382E]">
                        Salir
                    </button>

                </form>

            </div>
        </header>

        {{-- MENSAJES --}}
        @if(session('success'))
            <div class="mx-4 mt-5 rounded-xl border border-green-200
                        bg-green-50 p-4 text-sm text-green-800
                        sm:mx-6 lg:mx-8"
                 role="status">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mx-4 mt-5 rounded-xl border border-red-200
                        bg-red-50 p-4 text-sm text-red-800
                        sm:mx-6 lg:mx-8"
                 role="alert">
                {{ session('error') }}
            </div>
        @endif

        {{-- CONTENIDO --}}
        <main class="p-4 sm:p-6 lg:p-8">
            @yield('content')
        </main>

    </div>

</div>

</body>
</html>