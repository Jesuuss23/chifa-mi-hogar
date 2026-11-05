<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <meta name="referrer" content="no-referrer">
    <title>Mi cuenta | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront-theme customer-surface customer-account">
    <a href="#account-profile" class="account-skip">Ir a mis datos</a>
    <header class="account-header">
        @php($accountLogo = \App\Models\StoreSetting::current()->logoUrl())
        <a href="{{ route('storefront.index') }}" class="account-brand">
            @if($accountLogo)<img src="{{ $accountLogo }}" alt="" width="48" height="48">@endif
            <span>CHIFA<br>MI HOGAR</span>
        </a>
        <a href="{{ route('storefront.index') }}" class="account-shop">← Seguir comprando</a>
    </header>
    <main class="account-main">
        <div class="account-welcome">
            <div>
                <p class="account-eyebrow">TU ESPACIO EN CHIFA MI HOGAR</p>
                <h1>Mi cuenta</h1>
                <p class="mt-2">Hola, <strong>{{ auth()->user()->name }}</strong></p>
                <p class="mt-2 text-sm text-foreground/75">Tus datos para la próxima entrega, tus pedidos y tus formas de entrar.</p>
            </div>
            <form method="POST" action="{{ route('customer.logout') }}">
                @csrf
                <button type="submit" class="account-logout">Cerrar sesión</button>
            </form>
        </div>

        @if(session('success'))
            <p role="status" class="account-notice">{{ session('success') }}</p>
        @endif

        <div class="account-grid">
            <aside class="account-sidebar" aria-label="Resumen de tu cuenta">
                <nav aria-label="Secciones de Mi cuenta" class="account-nav">
                    <a href="#account-profile"><span aria-hidden="true">01</span> Mis datos y entrega</a>
                    <a href="#account-orders"><span aria-hidden="true">02</span> Mis pedidos</a>
                    <a href="#account-security"><span aria-hidden="true">03</span> Acceso y seguridad</a>
                </nav>
                <div class="account-summary">
                    <p class="account-eyebrow">CORREO DE TU CUENTA</p>
                    <p class="mt-2 text-sm">{{ auth()->user()->email }}</p>
                    <a href="#account-avatar" class="mt-3 inline-block text-sm underline">Mi foto de perfil →</a>
                    <p class="mt-5 account-eyebrow">PARA TU PRÓXIMO PEDIDO</p>
                    <p class="mt-2 text-sm">Completa o revisa tu dirección. Puedes utilizar otra al comprar.</p>
                    <a href="#account-profile" class="mt-3 inline-block underline">Revisar mis datos →</a>
                </div>
            </aside>
            <div class="account-content">
                @include('customer.avatar')
                @include('customer.profile')

                @php
                    $accountStatusLabels = [
                        'pending' => 'Pedido recibido',
                        'confirmed' => 'Pedido confirmado',
                        'preparing' => 'Preparando tu comida',
                        'on_the_way' => 'En camino',
                        'delivered' => 'Pedido entregado',
                        'cancelled' => 'Pedido cancelado',
                    ];
                @endphp
                @include('customer.account-orders')

                <section id="account-security" class="account-security" aria-labelledby="security-title" tabindex="-1">
                    <div class="account-section-heading">
                        <div><p class="account-eyebrow">TÚ CONTROLAS EL ACCESO</p><h2 id="security-title" class="text-xl font-bold">Acceso y seguridad</h2></div>
                    </div>
                    <p class="mt-3 text-sm text-foreground/75">Gestiona tu contraseña y las cuentas que usas para iniciar sesión.</p>
                    @include('customer.password-account')
                    @include('customer.google-account')
                    @include('customer.facebook-account')
                    @include('customer.social-management')
                </section>
            </div>
        </div>
        <footer class="account-footer"><a href="{{ route('storefront.index') }}">Volver a la carta →</a></footer>
    </main>
</body>
</html>
