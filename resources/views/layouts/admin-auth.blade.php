<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title', 'Administración') | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <link rel="icon" type="image/png" href="{{ asset('favicon.png') }}?v=2">
    <link rel="shortcut icon" href="{{ asset('favicon.png') }}?v=2">
</head>
<body data-admin-theme="sage" class="storefront-theme admin-sage customer-surface admin-auth antialiased">
    <a href="#admin-access" class="admin-auth-skip">Ir al formulario</a>
    <header class="admin-auth-header">
        @php($adminAuthLogo = \App\Models\StoreSetting::current()->logoUrl())
        <a href="{{ route('storefront.index') }}" class="admin-auth-brand">
            @if($adminAuthLogo)
                <img src="{{ $adminAuthLogo }}" alt="" width="48" height="48">
            @endif
            <span>CHIFA<br>MI HOGAR</span>
        </a>
        <a href="{{ route('storefront.index') }}" class="admin-auth-back">← Volver al restaurante</a>
    </header>
    <main id="admin-access" class="admin-auth-main" tabindex="-1">
        @yield('content')
    </main>
    <footer class="admin-auth-footer">Chifa Mi Hogar · Administración</footer>
</body>
</html>
