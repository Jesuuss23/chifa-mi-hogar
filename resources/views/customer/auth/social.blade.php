@include('customer.auth.google')
@include('customer.auth.facebook')
@if(\App\Services\GoogleCustomerLogin::enabled() || \App\Services\FacebookCustomerLogin::enabled())
    <details class="auth-data-note">
        <summary>¿Qué datos compartes?</summary>
        <p>Usamos tu nombre y correo disponible, nunca tu contraseña del proveedor.
            @if(\App\Models\StoreSetting::current()->legalPagesAvailable())
                <a href="{{ route('legal.privacy') }}">Privacidad</a>.
            @endif
        </p>
    </details>
    <p class="auth-divider">o con tu correo</p>
@endif
