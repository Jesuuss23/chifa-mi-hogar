<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if(config('turnstile.enabled') && config('turnstile.site_key'))
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
</head>
<body class="storefront-theme customer-surface min-h-screen bg-background flex items-center justify-center p-4">

    <div class="w-full max-w-md rounded-none bg-card p-8 shadow-storefront">
        <a href="{{ route('storefront.index') }}"
           class="text-sm text-primary">
            ← Volver al restaurante
        </a>

        <h1 class="mt-5 text-2xl font-bold text-foreground">
            Crear mi cuenta
        </h1>

        <p class="mt-2 text-sm text-foreground/75">
            Regístrate para consultar tus pedidos y valorar tus compras.
        </p>

        <form action="{{ route('customer.register.store') }}"
              method="POST"
              class="mt-6 space-y-4">
            @csrf
            @include('legal.collection-notice')

            <div>
                <label class="block text-sm font-medium">Nombre</label>
                <input type="text" name="name" required
                       value="{{ old('name') }}"
                       autocomplete="name"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3">
                @error('name')
                    <p class="mt-1 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Correo electrónico</label>
                <input type="email" name="email" required
                       value="{{ old('email') }}"
                       autocomplete="email"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3">
                @error('email')
                    <p class="mt-1 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Contraseña</label>
                <input type="password" name="password" required
                       autocomplete="new-password"
                       minlength="8"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3">
                @error('password')
                    <p class="mt-1 text-sm text-destructive">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Confirmar contraseña
                </label>
                <input type="password" name="password_confirmation" required
                       autocomplete="new-password"
                       minlength="8"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3">
            </div>

            @if(config('turnstile.enabled'))
                @if(config('turnstile.site_key'))
                    <div class="cf-turnstile"
                         data-sitekey="{{ config('turnstile.site_key') }}"
                         data-action="register" data-language="es"></div>
                    <noscript><p class="text-sm text-destructive">Activa JavaScript para completar la verificación.</p></noscript>
                @else
                    <p class="text-sm text-destructive">El registro no está disponible temporalmente. Inténtalo más tarde.</p>
                @endif
            @endif
            @error('cf-turnstile-response')
                <p role="alert" class="text-sm text-destructive">{{ $message }}</p>
            @enderror

            <button type="submit"
                    class="w-full rounded-none bg-primary p-3 font-semibold text-primary-foreground hover:bg-primary/90">
                Crear cuenta
            </button>
        </form>

        <p class="mt-6 text-center text-sm">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="font-semibold text-primary">
                Inicia sesión
            </a>
        </p>
    </div>
</body>
</html>