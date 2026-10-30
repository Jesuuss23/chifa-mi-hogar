<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">

    <title>Acceso repartidores | Chifa Mi Hogar</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="storefront-theme min-h-screen bg-background flex items-center justify-center p-4">

    <main class="catalog-surface [overflow-wrap:anywhere] w-full max-w-md border border-border bg-card rounded-none shadow-storefront p-7 space-y-6">

        <div class="text-center space-y-2">
            <h1 class="text-2xl font-bold text-foreground">
                Chifa Mi Hogar
            </h1>

            <p class="text-foreground/75">
                Acceso exclusivo para repartidores
            </p>
        </div>

        @if($errors->any())
            <div role="alert" class="rounded-none bg-destructive/10 p-3 text-destructive text-sm">
                {{ $errors->first() }}
            </div>
        @endif

        <form
            method="POST"
            action="{{ route('delivery.login.submit') }}"
            class="space-y-4"
        >
            @csrf

            <div>
                <label for="email" class="block text-sm font-medium mb-1">
                    Correo electrónico
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    class="w-full rounded-none border border-input bg-card text-foreground p-3"
                >
            </div>

            <div>
                <label for="password" class="block text-sm font-medium mb-1">
                    Contraseña
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    class="w-full rounded-none border border-input bg-card text-foreground p-3"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-none bg-primary text-primary-foreground py-3 font-semibold"
            >
                Iniciar sesión
            </button>
        </form>

        <p class="text-xs text-foreground/75 text-center">
            Tu cuenta debe ser creada por un administrador.
        </p>

    </main>

</body>
</html>
