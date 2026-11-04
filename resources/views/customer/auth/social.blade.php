@include('customer.auth.google')
@include('customer.auth.facebook')
@if(\App\Services\GoogleCustomerLogin::enabled() || \App\Services\FacebookCustomerLogin::enabled())
    <p class="mt-5 text-center text-sm text-foreground/75">O continúa con tu correo y contraseña</p>
@endif
