@error('google')
    <p role="alert" class="mt-4 border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">{{ $message }}</p>
@enderror

@if(\App\Services\GoogleCustomerLogin::enabled())
    <form method="POST" action="{{ route('customer.google.start') }}" class="mt-6 space-y-3">
        @csrf
        <button type="submit" class="flex min-h-12 w-full items-center justify-center border border-border bg-card px-4 py-3 font-semibold text-foreground hover:bg-accent">
            Continuar con Google
        </button>
        <p class="text-xs text-foreground/75">
            Usaremos tu nombre y correo verificado para crear tu cuenta o iniciar sesión.
            Google no comparte tu contraseña con nosotros.
        </p>
        @include('legal.collection-notice')
    </form>
    <p class="mt-5 text-center text-sm text-foreground/75">O continúa con tu correo y contraseña</p>
@endif
