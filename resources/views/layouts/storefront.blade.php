
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="theme-color" content="#982E26">

    <meta
        name="description"
        content="@yield('meta-description', 'Chifa Mi Hogar: disfruta deliciosos platos de chifa con delivery en San Juan de Lurigancho, Lima.')"
    >

    <title>
        @yield('title', 'Chifa Mi Hogar | Sabor auténtico')
    </title>

    {{-- Metadatos específicos de cada producto --}}
    @stack('head')

    {{-- Estilos adicionales --}}
    @stack('styles')

    @vite([
        'resources/css/app.css',
        'resources/js/app.js'
    ])
</head>

@php
    /*
    |--------------------------------------------------------------------------
    | INFORMACIÓN DEL USUARIO
    |--------------------------------------------------------------------------
    */

    $currentUser = auth()->user();

    $userRole = $currentUser?->role;

    /*
    |--------------------------------------------------------------------------
    | RUTAS SEGÚN EL ROL
    |--------------------------------------------------------------------------
    */

    $accountRoute = match ($userRole) {
        'customer' => 'customer.account',
        'admin' => 'admin.dashboard',
        'delivery' => 'delivery.orders.index',
        default => null,
    };

    $logoutRoute = match ($userRole) {
        'customer' => 'customer.logout',
        'admin' => 'admin.logout',
        'delivery' => 'delivery.logout',
        default => null,
    };

    /*
    |--------------------------------------------------------------------------
    | CONTADOR DEL CARRITO
    |--------------------------------------------------------------------------
    |
    | Cuenta las unidades de todas las configuraciones
    | almacenadas en la sesión.
    |
    */

    $cartData = session('cart', []);

    $cartCount = collect(
        is_array($cartData) ? $cartData : []
    )->sum(
        fn ($item) =>
            is_array($item)
                ? max(0, (int) ($item['quantity'] ?? 0))
                : 0
    );
@endphp

<body class="flex min-h-screen flex-col bg-[#FAF8F5] text-[#26211F] antialiased">

    {{-- =====================================================
         BARRA SUPERIOR
    ====================================================== --}}

    <div class="bg-[#982E26] text-white">

        <div
            class="mx-auto flex min-h-8 max-w-7xl items-center justify-center gap-2 px-4 py-1.5 text-center text-[11px] font-semibold tracking-wide sm:text-xs"
        >
            <svg
                xmlns="http://www.w3.org/2000/svg"
                width="15"
                height="15"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="1.8"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
            >
                <path d="M10 17h4V5H2v12h3"/>
                <path d="M14 9h4l4 4v4h-3"/>
                <circle cx="7.5" cy="17.5" r="2.5"/>
                <circle cx="16.5" cy="17.5" r="2.5"/>
            </svg>

            <span>
                Solo delivery · Delicioso y directo a tu puerta
            </span>
        </div>

    </div>

    {{-- =====================================================
         ENCABEZADO PRINCIPAL
    ====================================================== --}}

    <header
        class="sticky top-0 z-40 border-b border-[#EBE3DB] bg-white/95 backdrop-blur-xl"
    >

        <div
            class="mx-auto flex h-[72px] max-w-7xl items-center justify-between gap-3 px-4 sm:px-6 lg:h-20"
        >

            {{-- IDENTIDAD DEL NEGOCIO --}}

            <a
                href="{{ route('storefront.index') }}"
                class="group flex min-w-0 shrink-0 items-center gap-2.5"
                aria-label="Chifa Mi Hogar, ir al inicio"
            >

                @if($brandLogoUrl)
                    <img src="{{ $brandLogoUrl }}" alt="" width="44" height="44"
                         class="h-11 w-11 shrink-0 object-contain">
                @else
                <span
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-[#982E26] text-sm font-black tracking-tight text-white shadow-sm transition group-hover:bg-[#B43A31] sm:h-11 sm:w-11"
                    aria-hidden="true"
                >
                    CM
                </span>
                @endif

                <span class="flex min-w-0 flex-col">

                    <span
                        class="whitespace-nowrap text-[15px] font-black leading-tight tracking-tight text-[#982E26] sm:text-lg"
                    >
                        Chifa Mi Hogar
                    </span>

                    <span
                        class="hidden text-[10px] font-semibold tracking-wider text-[#806E65] sm:block"
                    >
                        SABOR QUE REÚNE
                    </span>

                </span>

            </a>

            {{-- NAVEGACIÓN DE ESCRITORIO --}}

            <nav
                class="hidden items-center gap-1 lg:flex"
                aria-label="Navegación principal"
            >

                <a
                    href="{{ route('storefront.index') }}"
                    @class([
                        'rounded-xl px-4 py-2.5 text-sm font-semibold transition',
                        'bg-[#FFF1EC] text-[#982E26]' => request()->routeIs('storefront.index'),
                        'text-gray-600 hover:bg-[#FAF5F1] hover:text-[#982E26]' => !request()->routeIs('storefront.index'),
                    ])
                >
                    Nuestra carta
                </a>

                <a
                    href="{{ route('combos.index') }}"
                    @class([
                        'rounded-xl px-4 py-2.5 text-sm font-semibold transition',
                        'bg-[#FFF1EC] text-[#982E26]' => request()->routeIs('combos.*'),
                        'text-gray-600 hover:bg-[#FAF5F1] hover:text-[#982E26]' => !request()->routeIs('combos.*'),
                    ])
                >
                    Combos
                </a>

            </nav>

            {{-- ACCIONES DEL ENCABEZADO --}}

            <div class="flex shrink-0 items-center gap-2 sm:gap-3">

                {{-- CUENTA DE USUARIO: ESCRITORIO --}}

                <div class="hidden items-center gap-2 md:flex">

                    @guest

                        <a
                            href="{{ route('login') }}"
                            class="rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-gray-100 hover:text-[#982E26]"
                        >
                            Iniciar sesión
                        </a>

                        <a
                            href="{{ route('customer.register') }}"
                            class="rounded-xl border border-[#E9D6CE] bg-[#FFF8F4] px-4 py-2.5 text-sm font-bold text-[#982E26] transition hover:bg-[#FDECE5]"
                        >
                            Registrarse
                        </a>

                    @else

                        @if($accountRoute)

                            <a
                                href="{{ route($accountRoute) }}"
                                class="flex items-center gap-2 rounded-xl px-3 py-2.5 text-sm font-semibold text-gray-700 transition hover:bg-[#FFF5F0] hover:text-[#982E26]"
                            >

                                <span
                                    class="flex h-8 w-8 items-center justify-center rounded-full bg-[#F4E6DF] text-xs font-black text-[#982E26]"
                                    aria-hidden="true"
                                >
                                    {{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}
                                </span>

                                <span class="hidden xl:inline">
                                    {{ $userRole === 'customer' ? 'Mi cuenta' : 'Mi panel' }}
                                </span>

                            </a>

                        @endif

                    @endguest

                </div>

                {{-- CARRITO --}}

                <a
                    href="{{ route('cart.index') }}"
                    class="relative inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-[#982E26] px-3 text-sm font-bold text-white shadow-sm transition hover:bg-[#B5382E] sm:px-4"
                    aria-label="Ver carrito, {{ $cartCount }} {{ $cartCount === 1 ? 'unidad' : 'unidades' }}"
                >

                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        width="20"
                        height="20"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="1.8"
                        stroke-linecap="round"
                        stroke-linejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="9" cy="21" r="1"/>
                        <circle cx="20" cy="21" r="1"/>
                        <path d="M1 1h4l2.5 12.5a2 2 0 0 0 2 1.5h9.7a2 2 0 0 0 2-1.6L23 6H6"/>
                    </svg>

                    <span class="hidden sm:inline">
                        Carrito
                    </span>

                    @if($cartCount > 0)

                        <span
                            class="flex min-h-5 min-w-5 items-center justify-center rounded-full bg-white px-1 text-[10px] font-black text-[#982E26]"
                        >
                            {{ $cartCount > 99 ? '99+' : $cartCount }}
                        </span>

                    @endif

                </a>

                {{-- MENÚ RESPONSIVE --}}

                <details class="group relative md:hidden">

                    <summary
                        class="flex h-11 w-11 cursor-pointer list-none items-center justify-center rounded-xl border border-[#EAE1D9] bg-white text-gray-800 transition hover:bg-[#FFF6F1] [&::-webkit-details-marker]:hidden"
                        aria-label="Abrir menú"
                    >

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                            class="group-open:hidden"
                        >
                            <path d="M4 7h16M4 12h16M4 17h16"/>
                        </svg>

                        <svg
                            xmlns="http://www.w3.org/2000/svg"
                            width="22"
                            height="22"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            aria-hidden="true"
                            class="hidden group-open:block"
                        >
                            <path d="M18 6 6 18M6 6l12 12"/>
                        </svg>

                    </summary>

                    <nav
                        class="absolute right-0 top-[calc(100%+12px)] z-50 w-[min(88vw,300px)] space-y-1 rounded-2xl border border-[#EBE1D9] bg-white p-3 shadow-xl"
                        aria-label="Menú móvil"
                    >

                        <a
                            href="{{ route('storefront.index') }}"
                            class="block rounded-xl px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-[#FFF5F0] hover:text-[#982E26]"
                        >
                            Nuestra carta
                        </a>

                        <a
                            href="{{ route('combos.index') }}"
                            class="block rounded-xl px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-[#FFF5F0] hover:text-[#982E26]"
                        >
                            Combos
                        </a>

                        <a
                            href="{{ route('cart.index') }}"
                            class="block rounded-xl px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-[#FFF5F0] hover:text-[#982E26]"
                        >
                            Mi carrito
                            @if($cartCount > 0)
                                ({{ $cartCount }})
                            @endif
                        </a>

                        <div class="my-2 border-t border-gray-100"></div>

                        @guest

                            <a
                                href="{{ route('login') }}"
                                class="block rounded-xl px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-[#FFF5F0]"
                            >
                                Iniciar sesión
                            </a>

                            <a
                                href="{{ route('customer.register') }}"
                                class="block rounded-xl bg-[#982E26] px-4 py-3 text-center text-sm font-bold text-white transition hover:bg-[#B5382E]"
                            >
                                Crear cuenta
                            </a>

                        @else

                            <p class="px-4 py-2 text-xs text-gray-500">
                                Sesión iniciada como
                                <span class="font-bold text-gray-800">
                                    {{ $currentUser->name }}
                                </span>
                            </p>

                            @if($accountRoute)

                                <a
                                    href="{{ route($accountRoute) }}"
                                    class="block rounded-xl px-4 py-3 text-sm font-semibold text-gray-800 transition hover:bg-[#FFF5F0]"
                                >
                                    {{ $userRole === 'customer' ? 'Mi cuenta' : 'Mi panel' }}
                                </a>

                            @endif

                            @if($logoutRoute)

                                <form
                                    method="POST"
                                    action="{{ route($logoutRoute) }}"
                                >
                                    @csrf

                                    <button
                                        type="submit"
                                        class="w-full rounded-xl px-4 py-3 text-left text-sm font-semibold text-red-700 transition hover:bg-red-50"
                                    >
                                        Cerrar sesión
                                    </button>

                                </form>

                            @endif

                        @endguest

                    </nav>

                </details>

            </div>

        </div>

        {{-- NAVEGACIÓN INTERMEDIA PARA TABLETS --}}

        <nav
            class="hidden border-t border-gray-100 px-4 py-2 md:flex md:items-center md:justify-center md:gap-8 lg:hidden"
            aria-label="Categorías principales"
        >

            <a
                href="{{ route('storefront.index') }}"
                class="text-sm font-semibold text-gray-600 hover:text-[#982E26]"
            >
                Nuestra carta
            </a>

            <a
                href="{{ route('combos.index') }}"
                class="text-sm font-semibold text-gray-600 hover:text-[#982E26]"
            >
                Combos
            </a>

        </nav>

    </header>

    {{-- =====================================================
         CONTENIDO
    ====================================================== --}}

    <main id="main-content" class="w-full flex-1">

        @yield('content')

    </main>

    {{-- =====================================================
         PIE DE PÁGINA
    ====================================================== --}}

    <footer
        class="mt-14 border-t border-[#EAE0D8] bg-white"
    >

        <div
            class="mx-auto grid max-w-7xl gap-10 px-4 pb-10 pt-12 sm:grid-cols-2 sm:px-6 lg:grid-cols-3 lg:gap-16 lg:pt-14"
        >

            {{-- IDENTIDAD --}}

            <div>

                <div class="flex items-center gap-3">

                    @if($brandLogoUrl)
                        <img src="{{ $brandLogoUrl }}" alt="" width="44" height="44"
                             class="h-11 w-11 shrink-0 object-contain">
                    @else
                    <div
                        class="flex h-11 w-11 items-center justify-center rounded-xl bg-[#982E26] text-sm font-black text-white"
                        aria-hidden="true"
                    >
                        CM
                    </div>
                    @endif

                    <div>

                        <p class="text-lg font-black text-[#982E26]">
                            Chifa Mi Hogar
                        </p>

                        <p class="text-[11px] font-semibold tracking-wide text-[#99857A]">
                            SABOR QUE REÚNE
                        </p>

                    </div>

                </div>

                <p class="mt-5 max-w-sm text-sm leading-7 text-gray-500">
                    Disfruta de nuestros platos preparados con
                    dedicación y el auténtico sabor de la casa,
                    directamente en la comodidad de tu hogar.
                </p>

            </div>

            {{-- ENLACES --}}

            <div>

                <h3 class="text-sm font-black uppercase tracking-wide text-gray-900">
                    Explora
                </h3>

                <nav class="mt-5 flex flex-col items-start gap-3">

                    <a
                        href="{{ route('storefront.index') }}"
                        class="text-sm text-gray-500 transition hover:text-[#982E26]"
                    >
                        Nuestra carta
                    </a>

                    <a
                        href="{{ route('combos.index') }}"
                        class="text-sm text-gray-500 transition hover:text-[#982E26]"
                    >
                        Nuestros combos
                    </a>

                    <a
                        href="{{ route('cart.index') }}"
                        class="text-sm text-gray-500 transition hover:text-[#982E26]"
                    >
                        Mi carrito
                    </a>

                    @guest

                        <a
                            href="{{ route('login') }}"
                            class="text-sm text-gray-500 transition hover:text-[#982E26]"
                        >
                            Iniciar sesión
                        </a>

                        <a
                            href="{{ route('customer.register') }}"
                            class="text-sm text-gray-500 transition hover:text-[#982E26]"
                        >
                            Registrarse
                        </a>

                    @else

                        @if($accountRoute)

                            <a
                                href="{{ route($accountRoute) }}"
                                class="text-sm text-gray-500 transition hover:text-[#982E26]"
                            >
                                Mi cuenta
                            </a>

                        @endif

                    @endguest

                </nav>

            </div>

            {{-- SERVICIO --}}

            <div>

                <h3 class="text-sm font-black uppercase tracking-wide text-gray-900">
                    Nuestro servicio
                </h3>

                <div class="mt-5 space-y-4">

                    <div class="flex items-start gap-3">

                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#FFF1EC] text-[#982E26]"
                            aria-hidden="true"
                        >
                            🛵
                        </span>

                        <div>

                            <p class="text-sm font-bold text-gray-800">
                                Delivery a domicilio
                            </p>

                            <p class="mt-1 text-xs leading-5 text-gray-500">
                                Recibe tus platos en la dirección que elijas.
                            </p>

                        </div>

                    </div>

                    <div class="flex items-start gap-3">

                        <span
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-[#FFF1EC] text-[#982E26]"
                            aria-hidden="true"
                        >
                            🍜
                        </span>

                        <div>

                            <p class="text-sm font-bold text-gray-800">
                                Preparado para ti
                            </p>

                            <p class="mt-1 text-xs leading-5 text-gray-500">
                                Personaliza cada plato con tus adicionales favoritos.
                            </p>

                        </div>

                    </div>

                </div>

            </div>

        </div>

        {{-- PARTE INFERIOR --}}

        <div class="border-t border-gray-100">

            <div
                class="mx-auto flex max-w-7xl flex-col items-center justify-between gap-3 px-4 py-5 text-center sm:flex-row sm:px-6 sm:text-left"
            >

                <p class="text-xs text-gray-400">
                    © {{ date('Y') }} Chifa Mi Hogar.
                    Todos los derechos reservados.
                </p>

                <p class="text-xs font-medium text-gray-400">
                    Preparado con sabor, entregado con cariño.
                </p>

            </div>

        </div>

    </footer>

    {{-- Scripts específicos de cada vista --}}
    @stack('scripts')

</body>
</html>
