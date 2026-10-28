<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Crear cuenta | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 flex items-center justify-center p-4">

    <div class="w-full max-w-md rounded-2xl bg-white p-8 shadow-lg">
        <a href="{{ route('storefront.index') }}"
           class="text-sm text-red-700">
            ← Volver al restaurante
        </a>

        <h1 class="mt-5 text-2xl font-bold text-slate-900">
            Crear mi cuenta
        </h1>

        <p class="mt-2 text-sm text-slate-500">
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
                       class="mt-1 w-full rounded-lg border p-3">
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Correo electrónico</label>
                <input type="email" name="email" required
                       value="{{ old('email') }}"
                       autocomplete="email"
                       class="mt-1 w-full rounded-lg border p-3">
                @error('email')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">Contraseña</label>
                <input type="password" name="password" required
                       autocomplete="new-password"
                       minlength="8"
                       class="mt-1 w-full rounded-lg border p-3">
                @error('password')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-medium">
                    Confirmar contraseña
                </label>
                <input type="password" name="password_confirmation" required
                       autocomplete="new-password"
                       minlength="8"
                       class="mt-1 w-full rounded-lg border p-3">
            </div>

            <button type="submit"
                    class="w-full rounded-lg bg-red-700 p-3 font-semibold text-white hover:bg-red-800">
                Crear cuenta
            </button>
        </form>

        <p class="mt-6 text-center text-sm">
            ¿Ya tienes cuenta?
            <a href="{{ route('login') }}" class="font-semibold text-red-700">
                Inicia sesión
            </a>
        </p>
    </div>
</body>
</html>