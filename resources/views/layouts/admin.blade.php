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

    <!-- Menú administrativo -->
    <aside class="bg-[#171717] text-white lg:w-64">
        <div class="border-b border-white/10 p-6">
            <h1 class="text-xl font-bold text-[#F5D28C]">
                Chifa Mi Hogar
            </h1>
            <p class="mt-1 text-xs text-gray-400">
                Panel administrativo
            </p>
        </div>

        <nav aria-label="Menú administrativo"
             class="flex gap-1 overflow-x-auto p-3
                    lg:flex-col lg:overflow-visible">

            <!-- Dashboard -->
            <a href="{{ route('admin.dashboard') }}"
            class="rounded-lg px-4 py-3 text-sm hover:bg-white/10">
                Dashboard
            </a>

            <!-- Pedidos: pendiente de implementar -->
            <span class="rounded-lg px-4 py-3 text-sm text-gray-500">
                Pedidos
            </span>

            <!-- Productos: pendiente de implementar -->
            <a href="{{ route('admin.products.index') }}"
            class="rounded-lg px-4 py-3 text-sm hover:bg-white/10
                    {{ request()->routeIs('admin.products.*') ? 'bg-[#982E26] text-white' : '' }}">
                Productos
            </a>

            <!-- Categorías: módulo funcional -->
            <a href="{{ route('admin.categories.index') }}"
            class="rounded-lg px-4 py-3 text-sm
                    hover:bg-white/10
                    {{ request()->routeIs('admin.categories.*') ? 'bg-[#982E26] text-white' : '' }}">
                Categorías
            </a>

            <!-- Adicionales: pendiente de implementar -->
            <a href="{{ route('admin.extras.index') }}"
            class="rounded-lg px-4 py-3 text-sm hover:bg-white/10
                    {{ request()->routeIs('admin.extras.*') ? 'bg-[#982E26] text-white' : '' }}">
                Adicionales
            </a>

            <a href="{{ route('admin.settings.edit') }}"
            class="rounded-lg px-4 py-3 text-sm hover:bg-white/10
                    {{ request()->routeIs('admin.settings.*') ? 'bg-[#982E26] text-white' : '' }}">
                Configuración general
            </a>
            <a href="{{ route('admin.whatsapp.index') }}"
            class="rounded-lg px-4 py-3 text-sm hover:bg-white/10
                    {{ request()->routeIs('admin.whatsapp.*') ? 'bg-[#982E26] text-white' : '' }}">
                WhatsApp Meta
            </a>
        </nav>
    </aside>

    <!-- Contenido principal -->
    <div class="min-w-0 flex-1">

        <header class="flex items-center justify-between
                       border-b bg-white px-6 py-4">

            <h2 class="font-semibold">
                @yield('page-title', 'Administración')
            </h2>

            <div class="flex items-center gap-4">
                <span class="hidden text-sm text-gray-600 sm:block">
                    {{ auth()->user()->name }}
                </span>

                <form method="POST"
                      action="{{ route('admin.logout') }}">
                    @csrf

                    <button type="submit"
                            class="rounded-lg bg-[#982E26]
                                   px-4 py-2 text-sm text-white
                                   hover:bg-[#B5382E]">
                        Salir
                    </button>
                </form>
            </div>
        </header>

        <main class="p-4 sm:p-6 lg:p-8">
            @yield('content')
        </main>

    </div>
</div>

</body>
</html>