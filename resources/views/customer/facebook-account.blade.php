@error('facebook')
    <p role="alert" class="mt-6 border border-destructive/30 bg-destructive/10 p-4 text-destructive">{{ $message }}</p>
@enderror
@if(session('facebook_status'))
    <p role="status" class="mt-6 border border-border bg-card p-4">{{ session('facebook_status') }}</p>
@endif
@if(\App\Services\FacebookCustomerLogin::enabled())
    <section class="mt-6 border border-border bg-card p-6">
        <h2 class="text-xl font-bold">Acceso con Facebook</h2>
        @if(auth()->user()->socialAccounts()->where('provider', 'facebook')->exists())
            <p class="mt-2 text-sm text-foreground/75">Tu cuenta está vinculada. Puedes usar Continuar con Facebook al iniciar sesión.</p>
        @else
            <p class="mt-2 text-sm text-foreground/75">
                Vincula la cuenta de Facebook que utiliza el mismo correo de tu perfil.
                Confirma tu contraseña actual para conservar tus pedidos y datos.
            </p>
            <form method="POST" action="{{ route('customer.facebook.link') }}" class="mt-4 space-y-3">
                @csrf
                <label for="facebook-link-password" class="block text-sm font-semibold">Contraseña actual</label>
                <input id="facebook-link-password" type="password" name="password" required autocomplete="current-password"
                       class="w-full border border-input bg-card p-3 text-foreground">
                @error('password')
                    <p role="alert" class="text-sm text-destructive">{{ $message }}</p>
                @enderror
                <button class="min-h-12 border border-border px-4 py-3 font-semibold hover:bg-accent" type="submit">Vincular Facebook</button>
            </form>
        @endif
    </section>
@endif
