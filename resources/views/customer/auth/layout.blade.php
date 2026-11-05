<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>@yield('title') | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('auth-head')
</head>
<body class="storefront-theme customer-surface customer-auth">
    <a href="#auth-content" class="auth-skip">Ir al formulario</a>
    <header class="auth-header">
        @php($authLogo = \App\Models\StoreSetting::current()->logoUrl())
        <a href="{{ route('storefront.index') }}" class="auth-brand">
            @if($authLogo)<img src="{{ $authLogo }}" alt="" width="48" height="48">@endif
            <span>CHIFA<br>MI HOGAR</span>
        </a>
        <a class="auth-back" href="{{ route('storefront.index') }}">← Volver al restaurante</a>
    </header>
    <main class="auth-shell">
        <section id="auth-content" class="auth-panel" aria-labelledby="auth-title" tabindex="-1">
            @if(request()->routeIs('login', 'customer.register'))
                <nav class="auth-tabs" aria-label="Acceso de clientes">
                    <a href="{{ route('login') }}" @if(request()->routeIs('login')) aria-current="page" @endif>Iniciar sesión</a>
                    <a href="{{ route('customer.register') }}" @if(request()->routeIs('customer.register')) aria-current="page" @endif>Crear cuenta</a>
                </nav>
            @endif
            @if(app(\App\Services\CustomerCheckoutReturn::class)->active(request()))
                <div class="mt-4 border border-border bg-muted p-4 text-sm">
                    <p>Después de ingresar o crear tu cuenta, volverás a tu pedido. Tu carrito se conserva.</p>
                    <a class="mt-2 inline-block font-semibold underline" href="{{ route('cart.checkout') }}">Volver al pedido sin iniciar sesión</a>
                </div>
            @endif
            @yield('content')
        </section>
        <aside class="auth-story" aria-labelledby="auth-story-title">
            <p class="auth-eyebrow">DE NUESTRA COCINA A TU HOGAR</p>
            <h2 id="auth-story-title">Tu próximo<br>antojo empieza<br><span>aquí.</span></h2>
            <p>Tu chifa de siempre, con tus datos a mano para el próximo pedido.</p>
            <ul class="auth-benefits">
                <li><span aria-hidden="true">01</span><div><strong>Tus pedidos, en un lugar</strong><p>Consulta tus compras desde Mi cuenta.</p></div></li>
                <li><span aria-hidden="true">02</span><div><strong>Menos datos, la próxima vez</strong><p>Guarda tu dirección y ajústala para cada entrega.</p></div></li>
                <li><span aria-hidden="true">03</span><div><strong>Tu opinión cuenta</strong><p>Valora tu experiencia después de recibir tu pedido.</p></div></li>
            </ul>
            <p class="auth-guest">¿Solo vienes por algo rico?<br><a href="{{ route('storefront.index') }}">Puedes comprar sin crear una cuenta →</a></p>
        </aside>
    </main>
    <footer class="auth-footer">
        <span>Chifa Mi Hogar · Hecho para compartir.</span>
        @if(\App\Models\StoreSetting::current()->legalPagesAvailable())
            <nav aria-label="Información legal">
                <a href="{{ route('legal.privacy') }}">Privacidad</a>
                <a href="{{ route('legal.cookies') }}">Cookies</a>
            </nav>
        @endif
    </footer>
</body>
</html>
