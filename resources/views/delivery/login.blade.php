<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">

    <title>Acceso repartidores | Chifa Mi Hogar</title>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center p-4">

    <main class="w-full max-w-md bg-white rounded-2xl shadow-lg p-7 space-y-6">

        <div class="text-center space-y-2">
            <h1 class="text-2xl font-bold text-slate-900">
                Chifa Mi Hogar
            </h1>

            <p class="text-slate-500">
                Acceso exclusivo para repartidores
            </p>
        </div>

        @if($errors->any())
            <div class="rounded-lg bg-red-50 p-3 text-red-700 text-sm">
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
                    class="w-full rounded-xl border border-slate-300 p-3"
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
                    class="w-full rounded-xl border border-slate-300 p-3"
                >
            </div>

            <button
                type="submit"
                class="w-full rounded-xl bg-slate-900 text-white py-3 font-semibold"
            >
                Iniciar sesión
            </button>
        </form>

        <p class="text-xs text-slate-500 text-center">
            Tu cuenta debe ser creada por un administrador.
        </p>

    </main>

</body>
</html>
