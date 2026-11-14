<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="referrer" content="no-referrer">
    <meta name="theme-color" content="#f7f8fa">
    <title>@yield('title', 'Administración') | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body data-admin-theme="sage" class="storefront-theme admin-sage admin-ui min-h-screen bg-background text-foreground">
<a href="#admin-content" class="admin-skip-link">Ir al contenido</a>
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a href="{{ route('admin.dashboard') }}" class="admin-brand">
            @if($brandLogoUrl)<img src="{{ $brandLogoUrl }}" alt="" width="44" height="44">@endif
            <span><strong>Chifa Mi Hogar</strong><small>Administración</small></span>
        </a>
        <details class="admin-navigation" open>
            <summary>Menú del panel</summary>
            <nav aria-label="Menú administrativo">
                <a href="{{ route('admin.dashboard') }}" class="admin-nav-link"
                   @if(request()->routeIs('admin.dashboard')) aria-current="page" @endif>Inicio</a>
                @php
                    $menuGroups = [
                        'Operación' => [
                            ['Pedidos', 'admin.orders.index', 'admin.orders.*'],
                            ['Clientes', 'admin.customers.index', 'admin.customers.*'],
                            ['Repartidores', 'admin.delivery-drivers.index', 'admin.delivery-drivers.*'],
                        ],
                        'Carta' => [
                            ['Productos', 'admin.products.index', 'admin.products.*'],
                            ['Combos', 'admin.combos.index', 'admin.combos.*'],
                            ['Categorías', 'admin.categories.index', 'admin.categories.*'],
                            ['Adicionales', 'admin.extras.index', 'admin.extras.*'],
                            ['Categorías de adicionales', 'admin.extra-categories.index', 'admin.extra-categories.*'],
                        ],
                        'Negocio' => [
                            ['Apariencia', 'admin.appearance.index', 'admin.appearance.*'],
                            ['Banners', 'admin.banners.index', 'admin.banners.*'],
                            ['Referidos', 'admin.referrals.index', 'admin.referrals.*'],
                            ['Configuración general', 'admin.settings.edit', 'admin.settings.*'],
                            ['WhatsApp Meta', 'admin.whatsapp.index', 'admin.whatsapp.*'],
                            ['Redes sociales', 'admin.social-links.index', 'admin.social-links.*'],
                        ],
                        'Opiniones' => [
                            ['Reseñas de Google', 'admin.reviews.google', 'admin.reviews.google*'],
                            ['Valoraciones de pedidos', 'admin.order-ratings.index', 'admin.order-ratings.*'],
                        ],
                    ];
                @endphp
                @foreach($menuGroups as $heading => $items)
                    <section aria-labelledby="admin-group-{{ $loop->index }}">
                        <h2 id="admin-group-{{ $loop->index }}" class="admin-nav-heading">{{ $heading }}</h2>
                        @foreach($items as [$label, $route, $pattern])
                            <a href="{{ route($route) }}" class="admin-nav-link"
                               @if(request()->routeIs($pattern)) aria-current="page" @endif>{{ $label }}</a>
                        @endforeach
                    </section>
                @endforeach
                <div class="admin-store-links">
                    <a href="{{ route('storefront.index') }}" target="_blank" rel="noopener noreferrer">Ver tienda pública ↗</a>
                    <a href="{{ route('combos.index') }}" target="_blank" rel="noopener noreferrer">Ver combos publicados ↗</a>
                </div>
            </nav>
        </details>
    </aside>
    <div class="admin-workspace">
        <header class="admin-topbar">
            <div><p class="admin-topbar-label">TU NEGOCIO, EN UN LUGAR</p><h2>@yield('page-title', 'Administración')</h2></div>
            <div class="admin-session">
                <span class="admin-user-name">{{ auth()->user()->name }}</span>
                <form method="POST" action="{{ route('admin.logout') }}">
                    @csrf
                    <button type="submit" class="admin-logout">Salir</button>
                </form>
            </div>
        </header>
        @if(session('success'))
            <div class="admin-message border border-green-200 bg-green-50 text-green-800" role="status">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="admin-message border border-destructive/30 bg-destructive/10 text-destructive" role="alert">{{ session('error') }}</div>
        @endif
        <main id="admin-content" class="admin-content" tabindex="-1">@yield('content')</main>
    </div>
</div>
</body>
</html>
