@include('customer.auth.google')
@include('customer.auth.facebook')
@if(\App\Services\GoogleCustomerLogin::enabled() || \App\Services\FacebookCustomerLogin::enabled())
    <p class="mt-3 text-xs leading-relaxed text-foreground/75">
        Usamos tu nombre y correo disponible, nunca tu contraseña del proveedor.
        @if(\App\Models\StoreSetting::current()->legalPagesAvailable())
            <a class="font-semibold underline" href="{{ route('legal.privacy') }}">Privacidad</a>.
        @endif
    </p>
    <p class="mt-5 text-center text-sm text-foreground/75">O continúa con tu correo y contraseña</p>
@endif
