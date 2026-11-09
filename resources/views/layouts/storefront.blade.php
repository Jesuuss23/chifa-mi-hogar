
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >

    <meta name="theme-color" content="{{ match ($storefrontTheme) { 'formal' => '#6a0a17', 'christmas' => '#103f33', 'summer' => '#086b70', 'sage' => '#b3bda4', default => '#af3a03' } }}">

    @if(isset($seo))
        @include('components.storefront-seo')
    @else
    <meta
        name="description"
        content="@yield('meta-description', 'Chifa Mi Hogar: disfruta deliciosos platos de chifa con delivery en San Juan de Lurigancho, Lima.')"
    >

    <title>
        @yield('title', 'Chifa Mi Hogar | Sabor auténtico')
    </title>

    @endif

    {{-- Metadatos específicos de cada producto --}}
    @stack('head')

    {{-- Estilos adicionales --}}
    @stack('styles')

    @if($themePreview)
        <meta name="robots" content="noindex,nofollow">
        @vite(['resources/css/app.css'])
    @else
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
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

<body @if($themePreview) inert @endif data-theme="{{ $storefrontTheme }}" class="storefront-theme customer-web flex min-h-screen flex-col bg-background text-foreground antialiased">

    <a href="#main-content" class="customer-skip">Ir al contenido</a>
    @include('storefront.partials.header')

    {{-- =====================================================
         CONTENIDO
    ====================================================== --}}

    @include('components.store-availability')

    <main id="main-content" tabindex="-1" class="w-full flex-1">

        @yield('content')

    </main>

    @include('storefront.partials.footer')

    {{-- Scripts específicos de cada vista --}}
    @stack('scripts')

</body>
</html>
