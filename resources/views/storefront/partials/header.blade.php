<header class="customer-header catalog-surface">
    <div class="customer-header__inner">
        <a href="{{ route('storefront.index') }}" class="customer-brand" aria-label="Chifa Mi Hogar, ir al inicio">
            @if($brandLogoUrl)
                <img src="{{ $brandLogoUrl }}" alt="" width="44" height="44">
            @else
                <span class="customer-brand__mark" aria-hidden="true">CM</span>
            @endif
            <span class="customer-brand__name">Chifa Mi Hogar<small>Solo delivery</small></span>
        </a>

        <nav class="customer-desktop-nav" aria-label="Navegación principal">
            <a href="{{ route('storefront.index') }}#carta" @if(request()->routeIs('storefront.index')) aria-current="page" @endif>Nuestra carta</a>
        </nav>

        <div class="customer-header__actions">
            <a class="customer-account-link" href="{{ $accountRoute ? route($accountRoute) : route('login') }}"
               aria-label="{{ $accountRoute ? ($userRole === 'customer' ? 'Mi cuenta' : 'Mi panel') : 'Iniciar sesión' }}">
                @if($userRole === 'customer' && $currentUser->avatar_path)
                    <img src="{{ route('customer.avatar.show') }}" alt="" width="32" height="32">
                @elseif($currentUser)
                    <span class="customer-account-initial" aria-hidden="true">{{ mb_strtoupper(mb_substr($currentUser->name, 0, 1)) }}</span>
                @else
                    <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/></svg>
                @endif
                <span>{{ $accountRoute ? ($userRole === 'customer' ? 'Mi cuenta' : 'Mi panel') : 'Ingresar' }}</span>
            </a>
            <a class="customer-cart-link" href="{{ route('cart.index') }}"
               aria-label="Ver carrito, {{ $cartCount }} {{ $cartCount === 1 ? 'unidad' : 'unidades' }}">
                <svg aria-hidden="true" width="21" height="21" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 3h2l3 12h11l3-9H6"/><circle cx="9" cy="20" r="1"/><circle cx="18" cy="20" r="1"/></svg>
                <span class="customer-cart-label">Carrito</span>
                @if($cartCount > 0)<span class="customer-cart-count">{{ $cartCount > 99 ? '99+' : $cartCount }}</span>@endif
            </a>
            <details class="customer-menu" data-customer-menu>
                <summary aria-label="Menú de navegación">
                    <svg aria-hidden="true" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                </summary>
                <nav aria-label="Menú móvil">
                    <a href="{{ route('storefront.index') }}#carta">Nuestra carta</a>
                    <a href="{{ route('cart.index') }}">Mi carrito @if($cartCount > 0)({{ $cartCount }})@endif</a>
                    @guest
                        <a href="{{ route('login') }}">Iniciar sesión</a>
                        <a href="{{ route('customer.register') }}">Crear cuenta</a>
                    @else
                        @if($accountRoute)<a href="{{ route($accountRoute) }}">{{ $userRole === 'customer' ? 'Mi cuenta' : 'Mi panel' }}</a>@endif
                        @if($logoutRoute)
                            <form method="POST" action="{{ route($logoutRoute) }}">@csrf<button type="submit">Cerrar sesión</button></form>
                        @endif
                    @endguest
                </nav>
            </details>
        </div>
    </div>
</header>
