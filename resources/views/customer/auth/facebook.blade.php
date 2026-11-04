@error('facebook')
    <p role="alert" class="mt-4 border border-destructive/30 bg-destructive/10 p-3 text-sm text-destructive">{{ $message }}</p>
@enderror

@if(\App\Services\FacebookCustomerLogin::enabled())
    <form method="POST" action="{{ route('customer.facebook.start') }}" class="mt-4 space-y-3">
        @csrf
        <button type="submit" class="flex min-h-12 w-full items-center justify-center border border-border bg-card px-4 py-3 font-semibold text-foreground hover:bg-accent">
            Continuar con Facebook
        </button>
        <p class="text-xs text-foreground/75">
            Usaremos tu nombre y el correo que Facebook comparta para crear tu cuenta o iniciar sesión.
            No recibimos tu contraseña de Facebook.
        </p>
        @include('legal.collection-notice')
    </form>
@endif
