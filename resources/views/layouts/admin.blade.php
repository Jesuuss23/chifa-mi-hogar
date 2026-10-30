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

<body class="storefront-theme min-h-screen bg-background text-foreground">

<div class="min-h-screen lg:flex">

    {{-- MENÚ ADMINISTRATIVO --}}
    <aside class="catalog-surface border-b border-sidebar-border bg-sidebar text-sidebar-foreground lg:w-72 lg:shrink-0 lg:border-r">

        {{-- Logo / Nombre --}}
        <div class="flex items-center gap-3 border-b border-sidebar-border p-6">
            @if($brandLogoUrl)
                <img src="{{ $brandLogoUrl }}" alt="" width="48" height="48"
                     class="h-12 w-12 shrink-0 object-contain">
            @endif
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-sidebar-primary">
                    Chifa Mi Hogar
                </h1>
                <p class="mt-1 text-xs text-foreground/75">
                    Panel administrativo
                </p>
            </div>
        </div>

        {{-- Navegación --}}
        <nav aria-label="Menú administrativo"
             class="flex gap-1 overflow-x-auto p-3
                    lg:flex-col lg:overflow-visible">

            {{-- DASHBOARD --}}
            <a href="{{ route('admin.dashboard') }}"
               @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.dashboard')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Dashboard
            </a>

            {{-- GESTIÓN DE PEDIDOS --}}
            <div class="hidden px-4 pb-1 pt-5 text-xs
                        font-bold uppercase tracking-wider
                        text-foreground/75 lg:block">
                Ventas
            </div>

            <a href="{{ route('admin.orders.index') }}"
               @if(request()->routeIs('admin.orders.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.orders.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Pedidos
            </a>

            {{-- REPARTIDORES --}}
            <a href="{{ route('admin.delivery-drivers.index') }}"
               @if(request()->routeIs('admin.delivery-drivers.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.delivery-drivers.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Repartidores
            </a>

            {{-- GESTIÓN DEL CATÁLOGO --}}
            <div class="hidden px-4 pb-1 pt-5 text-xs
                        font-bold uppercase tracking-wider
                        text-foreground/75 lg:block">
                Catálogo
            </div>

            {{-- Productos --}}
            <a href="{{ route('admin.products.index') }}"
               @if(request()->routeIs('admin.products.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.products.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Productos
            </a>

            {{-- Combos --}}
            <a href="{{ route('admin.combos.index') }}"
               @if(request()->routeIs('admin.combos.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.combos.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Combos
            </a>

            {{-- Categorías --}}
            <a href="{{ route('admin.categories.index') }}"
               @if(request()->routeIs('admin.categories.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.categories.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Categorías
            </a>

            {{-- Adicionales --}}
            <a href="{{ route('admin.extras.index') }}"
               @if(request()->routeIs('admin.extras.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.extras.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Adicionales
            </a>

            {{-- Categorías de adicionales --}}
            <a href="{{ route('admin.extra-categories.index') }}"
               @if(request()->routeIs('admin.extra-categories.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.extra-categories.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Categorías de adicionales
            </a>

            {{-- CONFIGURACIÓN --}}
            <div class="hidden px-4 pb-1 pt-5 text-xs
                        font-bold uppercase tracking-wider
                        text-foreground/75 lg:block">
                Configuración
            </div>

            {{-- Configuración general --}}
            <a href="{{ route('admin.settings.edit') }}"
               @if(request()->routeIs('admin.settings.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.settings.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Configuración general
            </a>

            {{-- WhatsApp --}}
            <a href="{{ route('admin.whatsapp.index') }}"
               @if(request()->routeIs('admin.whatsapp.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.whatsapp.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                WhatsApp Meta
            </a>

            {{-- Redes sociales --}}
            <a href="{{ route('admin.social-links.index') }}"
               @if(request()->routeIs('admin.social-links.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.social-links.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Redes sociales
            </a>

            {{-- RESEÑAS --}}
            <div class="hidden px-4 pb-1 pt-5 text-xs
                        font-bold uppercase tracking-wider
                        text-foreground/75 lg:block">
                Reseñas
            </div>

            {{-- Reseñas de Google --}}
            <a href="{{ route('admin.reviews.google') }}"
               @if(request()->routeIs('admin.reviews.google*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.reviews.google*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Reseñas de Google
            </a>

            {{-- Valoraciones de pedidos --}}
            <a href="{{ route('admin.order-ratings.index') }}"
               @if(request()->routeIs('admin.order-ratings.*')) aria-current="page" @endif
               class="shrink-0 whitespace-nowrap rounded-none px-4 py-3 text-sm transition
                      {{ request()->routeIs('admin.order-ratings.*')
                          ? 'bg-sidebar-primary text-sidebar-primary-foreground hover:bg-sidebar-primary/90'
                          : 'hover:bg-sidebar-accent' }}">
                Valoraciones de pedidos
            </a>

            {{-- ACCESO A LA TIENDA --}}
            <div class="mt-4 border-t border-sidebar-border pt-4">

                <a href="{{ route('storefront.index') }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="block rounded-none px-4 py-3 text-sm
                          font-semibold text-sidebar-primary
                          transition hover:bg-sidebar-accent">
                    Ver tienda pública ↗
                </a>

                <a href="{{ route('combos.index') }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="block rounded-none px-4 py-3 text-sm
                          text-sidebar-foreground transition
                          hover:bg-sidebar-accent">
                    Ver combos publicados ↗
                </a>

            </div>

        </nav>
    </aside>

    {{-- CONTENIDO PRINCIPAL --}}
    <div class="min-w-0 flex-1">

        {{-- HEADER --}}
        <header class="catalog-surface flex flex-wrap items-center justify-between
                       gap-4 border-b border-border bg-card px-4 py-4 sm:px-6">

            <h2 class="font-semibold">
                @yield('page-title', 'Administración')
            </h2>

            <div class="flex items-center gap-4">

                {{-- Usuario autenticado --}}
                <span class="hidden text-sm text-foreground/75 sm:block">
                    {{ auth()->user()->name }}
                </span>

                {{-- Cerrar sesión --}}
                <form method="POST"
                      action="{{ route('admin.logout') }}">

                    @csrf

                    <button type="submit"
                            class="rounded-none bg-primary
                                   px-4 py-2 text-sm font-semibold
                                   text-primary-foreground transition
                                   hover:bg-primary/90">
                        Salir
                    </button>

                </form>

            </div>
        </header>

        {{-- MENSAJES --}}
        @if(session('success'))
            <div class="mx-4 mt-5 rounded-none border border-green-200
                        bg-green-50 p-4 text-sm text-green-800
                        sm:mx-6 lg:mx-8"
                 role="status">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mx-4 mt-5 rounded-none border border-destructive/30
                        bg-destructive/10 p-4 text-sm text-destructive
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