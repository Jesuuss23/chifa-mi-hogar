@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-6xl space-y-6 px-4 py-8">

    <div>
        <h1 class="text-3xl font-bold text-slate-900">
            Administración de WhatsApp
        </h1>
        <p class="mt-2 text-sm text-slate-500">
            Configura quién controla la atención y recibe los pedidos.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-xl border border-green-200 bg-green-50 p-4 text-green-700">
            {{ session('success') }}
        </div>
    @endif

    @if($errors->any())
        <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-700">
            <p class="font-semibold">No se pudieron guardar los cambios:</p>
            <ul class="mt-2 list-inside list-disc text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- INFORMACIÓN DEL NÚMERO TÉCNICO --}}
    <section class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="text-xl font-bold text-slate-900">
            WhatsApp Cloud API de Meta
        </h2>

        <p class="mt-2 text-sm text-slate-500">
            El número técnico envía notificaciones y recibe los comandos.
            Los números registrados debajo son los administradores autorizados.
        </p>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase text-slate-500">
                    Identificador del número de Meta
                </p>
                <p class="mt-2 break-all font-mono text-sm">
                    {{ config('meta_whatsapp.phone_number_id') ?: 'No configurado' }}
                </p>
            </div>

            <div class="rounded-xl bg-slate-50 p-4">
                <p class="text-xs font-semibold uppercase text-slate-500">
                    Webhook de recepción
                </p>
                <p class="mt-2 break-all font-mono text-sm">
                    {{ url('/api/webhooks/meta/whatsapp') }}
                </p>
            </div>
        </div>

        <div class="mt-4 rounded-xl bg-amber-50 p-4 text-sm text-amber-800">
            Las credenciales privadas se configuran en el servidor.
            Nunca compartas el token de acceso ni el secreto de Meta.
        </div>
    </section>

    {{-- REGISTRAR ADMINISTRADOR --}}
    <section class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="mb-2 text-xl font-bold">
            Agregar administrador
        </h2>
        <p class="mb-6 text-sm text-slate-500">
            Registra el celular que utilizará WhatsApp para recibir
            notificaciones o controlar la tienda.
        </p>

        <form method="POST"
              action="{{ route('admin.whatsapp.admins.store') }}"
              class="space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-semibold">
                        Nombre *
                    </label>
                    <input
                        type="text"
                        name="name"
                        required
                        maxlength="120"
                        value="{{ old('name') }}"
                        placeholder="Ejemplo: Encargado de pedidos"
                        class="w-full rounded-xl border border-slate-300 p-3">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-semibold">
                        Número de WhatsApp *
                    </label>
                    <input
                        type="tel"
                        name="phone"
                        required
                        value="{{ old('phone') }}"
                        placeholder="51987654321"
                        class="w-full rounded-xl border border-slate-300 p-3">
                    <p class="mt-1 text-xs text-slate-500">
                        Número peruano con prefijo 51.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <label class="flex items-start gap-3 rounded-xl border p-4">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', '1')) class="mt-1">
                    <span>
                        <span class="block font-semibold">Activo</span>
                        <span class="text-xs text-slate-500">
                            Número habilitado
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-xl border p-4">
                    <input type="hidden" name="can_control_store" value="0">
                    <input type="checkbox" name="can_control_store" value="1"
                           @checked(old('can_control_store', '0')) class="mt-1">
                    <span>
                        <span class="block font-semibold">Controlar tienda</span>
                        <span class="text-xs text-slate-500">
                            Abrir y cerrar pedidos
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-xl border p-4">
                    <input type="hidden" name="receive_orders" value="0">
                    <input type="checkbox" name="receive_orders" value="1"
                           @checked(old('receive_orders', '1')) class="mt-1">
                    <span>
                        <span class="block font-semibold">Recibir pedidos</span>
                        <span class="text-xs text-slate-500">
                            Notificaciones futuras
                        </span>
                    </span>
                </label>
            </div>

            <button type="submit"
                    class="rounded-xl bg-orange-600 px-6 py-3 font-semibold text-white hover:bg-orange-700">
                + Registrar administrador
            </button>
        </form>
    </section>

    {{-- LISTADO --}}
    <section class="space-y-4">
        <h2 class="text-xl font-bold">
            Administradores registrados ({{ $admins->count() }})
        </h2>

        @forelse($admins as $admin)
            <form method="POST"
                  action="{{ route('admin.whatsapp.admins.update', $admin) }}"
                  class="space-y-5 rounded-2xl border bg-white p-6 shadow-sm">
                @csrf
                @method('PUT')

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-bold text-slate-900">
                        Administrador #{{ $admin->id }}
                    </h3>

                    <span class="rounded-full px-3 py-1 text-xs font-semibold
                        {{ $admin->is_active
                            ? 'bg-green-100 text-green-700'
                            : 'bg-red-100 text-red-700' }}">
                        {{ $admin->is_active ? 'Activo' : 'Desactivado' }}
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-semibold">
                            Nombre
                        </label>
                        <input type="text" name="name" required
                               maxlength="120"
                               value="{{ $admin->name }}"
                               class="w-full rounded-xl border border-slate-300 p-3">
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-semibold">
                            Teléfono
                        </label>
                        <input type="tel" name="phone" required
                               value="{{ $admin->phone }}"
                               class="w-full rounded-xl border border-slate-300 p-3">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="flex items-center gap-2 rounded-xl bg-slate-50 p-3 text-sm">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                               @checked($admin->is_active)>
                        Activo
                    </label>

                    <label class="flex items-center gap-2 rounded-xl bg-slate-50 p-3 text-sm">
                        <input type="hidden" name="can_control_store" value="0">
                        <input type="checkbox" name="can_control_store" value="1"
                               @checked($admin->can_control_store)>
                        Controlar tienda
                    </label>

                    <label class="flex items-center gap-2 rounded-xl bg-slate-50 p-3 text-sm">
                        <input type="hidden" name="receive_orders" value="0">
                        <input type="checkbox" name="receive_orders" value="1"
                               @checked($admin->receive_orders)>
                        Recibir pedidos
                    </label>
                </div>

                <div class="flex flex-wrap items-center justify-between gap-3 border-t pt-4">
                    <a href="https://wa.me/{{ $admin->phone }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="text-sm font-semibold text-green-700 underline">
                        Abrir WhatsApp
                    </a>

                    <button type="submit"
                            class="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white">
                        Guardar cambios
                    </button>
                </div>
            </form>
        @empty
            <div class="rounded-xl border bg-white p-8 text-center text-slate-500">
                No hay administradores registrados.
            </div>
        @endforelse
    </section>

    {{-- HISTORIAL --}}
    <section class="rounded-2xl border bg-white p-6 shadow-sm">
        <h2 class="mb-2 text-xl font-bold">
            Historial de comandos
        </h2>
        <p class="mb-5 text-sm text-slate-500">
            Últimos comandos procesados por el webhook de Meta.
        </p>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-left text-sm">
                <thead class="bg-slate-50 text-slate-600">
                    <tr>
                        <th class="p-3">Fecha</th>
                        <th class="p-3">Remitente</th>
                        <th class="p-3">Comando</th>
                        <th class="p-3">Resultado</th>
                    </tr>
                </thead>
                <tbody class="divide-y">
                    @forelse($logs as $log)
                        <tr>
                            <td class="p-3">
                                {{ $log->created_at->format('d/m/Y H:i') }}
                            </td>
                            <td class="p-3">
                                {{ $log->sender_phone }}
                            </td>
                            <td class="p-3 font-semibold">
                                {{ $log->command }}
                            </td>
                            <td class="p-3">
                                @if($log->result === 'open')
                                    <span class="text-green-700">Abierto</span>
                                @elseif($log->result === 'closed')
                                    <span class="text-orange-700">Cerrado</span>
                                @else
                                    {{ $log->result }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="p-8 text-center text-slate-500">
                                Todavía no se han recibido comandos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- COMANDOS --}}
    <section class="rounded-2xl border bg-slate-900 p-6 text-white">
        <h2 class="mb-4 text-xl font-bold">
            Comandos disponibles
        </h2>

        <div class="space-y-3 text-sm">
            <div class="rounded-xl bg-white/10 p-3">
                <code class="font-bold">ABRIR CHIFA</code>
                <p class="mt-1 text-slate-300">
                    Habilita la recepción de nuevos pedidos.
                </p>
            </div>

            <div class="rounded-xl bg-white/10 p-3">
                <code class="font-bold">CERRAR CHIFA</code>
                <p class="mt-1 text-slate-300">
                    Deshabilita la recepción de nuevos pedidos.
                </p>
            </div>

            <div class="rounded-xl bg-white/10 p-3">
                <code class="font-bold">ESTADO CHIFA</code>
                <p class="mt-1 text-slate-300">
                    Consulta el estado. La respuesta automática
                    se habilitará al conectar el servicio de envío.
                </p>
            </div>
        </div>
    </section>
</div>
@endsection