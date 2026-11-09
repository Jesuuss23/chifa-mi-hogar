@php($contactEmail = \App\Models\StoreSetting::current()->privacy_email)
<footer class="customer-footer catalog-surface">
    <div class="customer-footer__main">
        <div class="customer-footer__brand">
            @if($brandLogoUrl)<img src="{{ $brandLogoUrl }}" alt="" width="56" height="56" loading="lazy">@endif
            <h2>Chifa Mi Hogar<span>El sabor de estar en casa.</span></h2>
            <p>Recién preparado. Solo delivery.</p>
            <a class="customer-footer__cta" href="{{ route('storefront.index') }}#carta">Elegir mi plato <span aria-hidden="true">↗</span></a>
        </div>
        <nav aria-label="Explorar">
            <h3>A tu gusto</h3>
            <a href="{{ route('storefront.index') }}#carta">Nuestra carta</a>
            <a href="{{ route('storefront.index') }}#combos">Combos y promociones</a>
            <a href="{{ route('cart.index') }}">Mi carrito</a>
            <a href="{{ $accountRoute ? route($accountRoute) : route('login') }}">{{ $accountRoute ? 'Mi cuenta' : 'Ingresar' }}</a>
        </nav>
        <div class="customer-footer__contact">
            <h3>Estamos cerca</h3>
            @if($contactEmail)<a href="mailto:{{ $contactEmail }}">{{ $contactEmail }}</a>@endif
            @if($legalPagesAvailable)
                <nav aria-label="Información legal">
                    <a href="{{ route('legal.notice') }}">Aviso legal</a>
                    <a href="{{ route('legal.privacy') }}">Privacidad</a>
                    <a href="{{ route('legal.cookies') }}">Cookies y preferencias</a>
                </nav>
            @endif
        </div>
    </div>
    <div class="customer-footer__bottom"><span>© {{ date('Y') }} Chifa Mi Hogar</span><span>Hecho para compartir.</span></div>
</footer>
