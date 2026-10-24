@extends('layouts.admin-auth')

@section('title', 'Iniciar sesión')

@section('content')

<div class="w-full max-w-md">

    <div class="text-center mb-8">

        <div class="mx-auto mb-4 flex h-16 w-16 items-center
                    justify-center rounded-2xl bg-[#80251F]
                    text-3xl text-[#F4D28C]">
            🥢
        </div>

        <h1 class="text-3xl font-bold text-[#F7E9D1]">
            Chifa Mi Hogar
        </h1>

        <p class="mt-2 text-xs font-semibold uppercase
                  tracking-[0.25em] text-[#D5AD75]">
            Panel administrativo
        </p>
    </div>

    <div class="rounded-2xl border border-white/10
                bg-[#222222] p-8 shadow-2xl">

        <h2 class="text-2xl font-bold">
            Bienvenido de nuevo
        </h2>

        <p class="mt-2 text-sm text-gray-400">
            Ingresa tus credenciales para continuar.
        </p>

        @if ($errors->any())
            <div role="alert"
                 class="mt-5 rounded-lg border border-red-500/30
                        bg-red-500/10 p-3 text-sm text-red-300">
                {{ $errors->first() }}
            </div>
        @endif

        <form method="POST"
              action="{{ route('admin.login.submit') }}"
              class="mt-7 space-y-5">

            @csrf

            <div>
                <label for="email"
                       class="mb-2 block text-sm font-medium">
                    Correo electrónico
                </label>

                <input
                    id="email"
                    type="email"
                    name="email"
                    value="{{ old('email') }}"
                    autocomplete="username"
                    required
                    autofocus
                    class="w-full rounded-lg border border-white/10
                           bg-[#303030] px-4 py-3 text-white
                           outline-none transition
                           focus:border-[#D5AD75]">
            </div>

            <div>
                <label for="password"
                       class="mb-2 block text-sm font-medium">
                    Contraseña
                </label>

                <input
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="current-password"
                    required
                    class="w-full rounded-lg border border-white/10
                           bg-[#303030] px-4 py-3 text-white
                           outline-none transition
                           focus:border-[#D5AD75]">
            </div>

            <button
                type="submit"
                class="w-full rounded-lg bg-[#982E26]
                       px-4 py-3 font-semibold text-white
                       transition hover:bg-[#B5382E]
                       focus-visible:outline-2
                       focus-visible:outline-offset-2
                       focus-visible:outline-[#D5AD75]">
                Iniciar sesión →
            </button>

        </form>
    </div>

    <p class="mt-6 text-center text-xs text-gray-500">
        Acceso exclusivo para personal autorizado
    </p>

</div>

@endsection