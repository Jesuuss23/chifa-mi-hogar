@extends('layouts.admin')

@section('content')
<div class="max-w-6xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold">
            Gestión de repartidores
        </h1>
        <p class="text-gray-500">
            Administra las cuentas del personal de reparto.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-lg bg-green-50 p-4 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-lg bg-red-50 p-4 text-red-800">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <section class="rounded-xl border bg-white p-6">
            <h2 class="text-lg font-bold mb-4">
                Registrar repartidor
            </h2>

            <form method="POST"
                  action="{{ route('admin.delivery-drivers.store') }}"
                  class="space-y-4">

                @csrf

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Nombre completo
                    </label>
                    <input
                        type="text"
                        name="name"
                        value="{{ old('name') }}"
                        maxlength="255"
                        required
                        class="w-full rounded-lg border-gray-300">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Correo electrónico
                    </label>
                    <input
                        type="email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        class="w-full rounded-lg border-gray-300">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Contraseña
                    </label>
                    <input
                        type="password"
                        name="password"
                        minlength="12"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-lg border-gray-300">
                </div>

                <div>
                    <label class="block text-sm font-medium mb-1">
                        Confirmar contraseña
                    </label>
                    <input
                        type="password"
                        name="password_confirmation"
                        minlength="12"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-lg border-gray-300">
                </div>

                <button
                    type="submit"
                    class="rounded-lg bg-slate-900 px-5 py-3 text-white">
                    Crear repartidor
                </button>
            </form>
        </section>

        <section class="rounded-xl border bg-white p-6">
            <h2 class="text-lg font-bold mb-4">
                Repartidores registrados
            </h2>

            @forelse($drivers as $driver)
                <div class="border-b py-3 last:border-0">
                    <p class="font-semibold">
                        {{ $driver->name }}
                    </p>
                    <p class="text-sm text-gray-500">
                        {{ $driver->email }}
                    </p>
                </div>
            @empty
                <p class="text-gray-500">
                    Todavía no hay repartidores registrados.
                </p>
            @endforelse
        </section>

    </div>
</div>
@endsection
