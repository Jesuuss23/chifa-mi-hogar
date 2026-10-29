<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar sesión | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront-theme customer-surface min-h-screen bg-background flex items-center justify-center p-4">

    <div class="w-full max-w-md rounded-none bg-card p-8 shadow-storefront">
        <a href="{{ route('storefront.index') }}"
           class="text-sm text-primary">
            ← Volver al restaurante
        </a>

        <h1 class="mt-5 text-2xl font-bold text-foreground">
            Bienvenido nuevamente
        </h1>

        <p class="mt-2 text-sm text-foreground/75">
            Ingresa para consultar tus pedidos.
        </p>

        <form action="{{ route('customer.login.store') }}"
              method="POST"
              class="mt-6 space-y-4">
            @csrf

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
                       autocomplete="current-password"
                       class="mt-1 w-full rounded-none border border-input bg-card text-foreground p-3">
            </div>

            <button type="submit"
                    class="w-full rounded-none bg-primary p-3 font-semibold text-primary-foreground hover:bg-primary/90">
                Iniciar sesión
            </button>
        </form>

        <p class="mt-6 text-center text-sm">
            ¿No tienes cuenta?
            <a href="{{ route('customer.register') }}"
               class="font-semibold text-primary">
                Regístrate
            </a>
        </p>
    </div>
</body>
</html>
