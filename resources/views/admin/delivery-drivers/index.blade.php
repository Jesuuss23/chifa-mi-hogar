@extends('layouts.admin')

@section('title', 'Repartidores')
@section('page-title', 'Repartidores')

@section('content')
<div class="catalog-surface admin-catalog-surface max-w-6xl mx-auto space-y-6">

    <div>
        <h1 class="text-2xl font-bold">
            Gestión de repartidores
        </h1>
        <p class="text-foreground/75">
            Administra las cuentas del personal de reparto.
        </p>
    </div>

    @if($errors->any())
        <div role="alert" class="rounded-none bg-destructive/10 p-4 text-destructive">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

        <section class="rounded-none border bg-card p-6">
            <h2 class="text-lg font-bold mb-4">
                Registrar repartidor
            </h2>

            <form method="POST"
                  action="{{ route('admin.delivery-drivers.store') }}"
                  class="space-y-4">

                @csrf

                <div>
                    <label for="driver-name" class="block text-sm font-medium mb-1">
                        Nombre completo
                    </label>
                    <input
                        type="text"
                        id="driver-name"
                        name="name"
                        value="{{ old('name') }}"
                        maxlength="255"
                        required
                        class="w-full rounded-none border-input">
                </div>

                <div>
                    <label for="driver-email" class="block text-sm font-medium mb-1">
                        Correo electrónico
                    </label>
                    <input
                        type="email"
                        id="driver-email"
                        name="email"
                        value="{{ old('email') }}"
                        required
                        class="w-full rounded-none border-input">
                </div>

                <div>
                    <label for="driver-password" class="block text-sm font-medium mb-1">
                        Contraseña
                    </label>
                    <input
                        type="password"
                        id="driver-password"
                        name="password"
                        minlength="12"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-none border-input">
                </div>

                <div>
                    <label for="driver-password_confirmation" class="block text-sm font-medium mb-1">
                        Confirmar contraseña
                    </label>
                    <input
                        type="password"
                        id="driver-password_confirmation"
                        name="password_confirmation"
                        minlength="12"
                        autocomplete="new-password"
                        required
                        class="w-full rounded-none border-input">
                </div>

                <button
                    type="submit"
                    class="rounded-none bg-primary px-5 py-3 text-primary-foreground">
                    Crear repartidor
                </button>
            </form>
        </section>

        <section class="rounded-none border bg-card p-6">
            <h2 class="text-lg font-bold mb-4">
                Repartidores registrados
            </h2>

            @forelse($drivers as $driver)
                <div class="border-b py-3 last:border-0">
                    <p class="font-semibold">
                        {{ $driver->name }}
                    </p>
                    <p class="text-sm text-foreground/75">
                        {{ $driver->email }}
                    </p>
                </div>
            @empty
                <p class="text-foreground/75">
                    Todavía no hay repartidores registrados.
                </p>
            @endforelse
        </section>

    </div>
</div>
@endsection
