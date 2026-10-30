@extends('layouts.admin')

@section('title', 'WhatsApp Meta')
@section('page-title', 'WhatsApp Meta')

@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-6xl space-y-6">

    <div>
        <h1 class="text-3xl font-bold text-foreground">
            Administración de WhatsApp
        </h1>
        <p class="mt-2 text-sm text-foreground/75">
            Configura quién controla la atención y recibe los pedidos.
        </p>
    </div>

    @if($errors->any())
        <div role="alert" class="rounded-none border border-destructive/30 bg-destructive/10 p-4 text-destructive">
            <p class="font-semibold">No se pudieron guardar los cambios:</p>
            <ul class="mt-2 list-inside list-disc text-sm">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    {{-- INFORMACIÓN DEL NÚMERO TÉCNICO --}}
    <section class="rounded-none border bg-card p-6 shadow-storefront">
        <h2 class="text-xl font-bold text-foreground">
            WhatsApp Cloud API de Meta
        </h2>

        <p class="mt-2 text-sm text-foreground/75">
            El número técnico envía notificaciones y recibe los comandos.
            Los números registrados debajo son los administradores autorizados.
        </p>

        <div class="mt-5 grid gap-4 md:grid-cols-2">
            <div class="rounded-none bg-muted p-4">
                <p class="text-xs font-semibold uppercase text-foreground/75">
                    Identificador del número de Meta
                </p>
                <p class="mt-2 break-all font-mono text-sm">
                    {{ config('meta_whatsapp.phone_number_id') ?: 'No configurado' }}
                </p>
            </div>

            <div class="rounded-none bg-muted p-4">
                <p class="text-xs font-semibold uppercase text-foreground/75">
                    Webhook de recepción
                </p>
                <p class="mt-2 break-all font-mono text-sm">
                    {{ url('/api/webhooks/meta/whatsapp') }}
                </p>
            </div>
        </div>

        <div class="mt-4 rounded-none bg-amber-50 p-4 text-sm text-amber-800">
            Las credenciales privadas se configuran en el servidor.
            Nunca compartas el token de acceso ni el secreto de Meta.
        </div>
    </section>

    {{-- REGISTRAR ADMINISTRADOR --}}
    <section class="rounded-none border bg-card p-6 shadow-storefront">
        <h2 class="mb-2 text-xl font-bold">
            Agregar administrador
        </h2>
        <p class="mb-6 text-sm text-foreground/75">
            Registra el celular que utilizará WhatsApp para recibir
            notificaciones o controlar la tienda.
        </p>

        <form method="POST"
              action="{{ route('admin.whatsapp.admins.store') }}"
              class="space-y-5">
            @csrf

            <div class="grid gap-5 md:grid-cols-2">
                <div>
                    <label for="whatsapp-create-name" class="mb-2 block text-sm font-semibold">
                        Nombre *
                    </label>
                    <input
                        type="text"
                        id="whatsapp-create-name"
                        name="name"
                        required
                        maxlength="120"
                        value="{{ old('name') }}"
                        placeholder="Ejemplo: Encargado de pedidos"
                        class="w-full rounded-none border border-input p-3">
                </div>

                <div>
                    <label for="whatsapp-create-phone" class="mb-2 block text-sm font-semibold">
                        Número de WhatsApp *
                    </label>
                    <input
                        type="tel"
                        id="whatsapp-create-phone"
                        name="phone"
                        required
                        value="{{ old('phone') }}"
                        placeholder="51987654321"
                        class="w-full rounded-none border border-input p-3">
                    <p class="mt-1 text-xs text-foreground/75">
                        Número peruano con prefijo 51.
                    </p>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <label class="flex items-start gap-3 rounded-none border p-4">
                    <input type="hidden" name="is_active" value="0">
                    <input type="checkbox" name="is_active" value="1"
                           @checked(old('is_active', '1')) class="mt-1">
                    <span>
                        <span class="block font-semibold">Activo</span>
                        <span class="text-xs text-foreground/75">
                            Número habilitado
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-none border p-4">
                    <input type="hidden" name="can_control_store" value="0">
                    <input type="checkbox" name="can_control_store" value="1"
                           @checked(old('can_control_store', '0')) class="mt-1">
                    <span>
                        <span class="block font-semibold">Controlar tienda</span>
                        <span class="text-xs text-foreground/75">
                            Abrir y cerrar pedidos
                        </span>
                    </span>
                </label>

                <label class="flex items-start gap-3 rounded-none border p-4">
                    <input type="hidden" name="receive_orders" value="0">
                    <input type="checkbox" name="receive_orders" value="1"
                           @checked(old('receive_orders', '1')) class="mt-1">
                    <span>
                        <span class="block font-semibold">Recibir pedidos</span>
                        <span class="text-xs text-foreground/75">
                            Notificaciones futuras
                        </span>
                    </span>
                </label>
            </div>

            <button type="submit"
                    class="rounded-none bg-primary px-6 py-3 font-semibold text-primary-foreground hover:bg-primary/90">
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
                  class="space-y-5 rounded-none border bg-card p-6 shadow-storefront">
                @csrf
                @method('PUT')

                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h3 class="font-bold text-foreground">
                        Administrador #{{ $admin->id }}
                    </h3>

                    <span class="rounded-none px-3 py-1 text-xs font-semibold
                        {{ $admin->is_active
                            ? 'bg-green-100 text-green-700'
                            : 'bg-destructive/10 text-destructive' }}">
                        {{ $admin->is_active ? 'Activo' : 'Desactivado' }}
                    </span>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="whatsapp-admin-{{ $admin->id }}-name" class="mb-2 block text-sm font-semibold">
                            Nombre
                        </label>
                        <input id="whatsapp-admin-{{ $admin->id }}-name" type="text" name="name" required
                               maxlength="120"
                               value="{{ $admin->name }}"
                               class="w-full rounded-none border border-input p-3">
                    </div>

                    <div>
                        <label for="whatsapp-admin-{{ $admin->id }}-phone" class="mb-2 block text-sm font-semibold">
                            Teléfono
                        </label>
                        <input id="whatsapp-admin-{{ $admin->id }}-phone" type="tel" name="phone" required
                               value="{{ $admin->phone }}"
                               class="w-full rounded-none border border-input p-3">
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-3">
                    <label class="flex items-center gap-2 rounded-none bg-muted p-3 text-sm">
                        <input type="hidden" name="is_active" value="0">
                        <input type="checkbox" name="is_active" value="1"
                               @checked($admin->is_active)>
                        Activo
                    </label>

                    <label class="flex items-center gap-2 rounded-none bg-muted p-3 text-sm">
                        <input type="hidden" name="can_control_store" value="0">
                        <input type="checkbox" name="can_control_store" value="1"
                               @checked($admin->can_control_store)>
                        Controlar tienda
                    </label>

                    <label class="flex items-center gap-2 rounded-none bg-muted p-3 text-sm">
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
                            class="rounded-none bg-primary px-5 py-3 text-sm font-semibold text-primary-foreground">
                        Guardar cambios
                    </button>
                </div>
            </form>
        @empty
            <div class="rounded-none border bg-card p-8 text-center text-foreground/75">
                No hay administradores registrados.
            </div>
        @endforelse
    </section>

    {{-- HISTORIAL --}}
    <section class="rounded-none border bg-card p-6 shadow-storefront">
        <h2 class="mb-2 text-xl font-bold">
            Historial de comandos
        </h2>
        <p class="mb-5 text-sm text-foreground/75">
            Últimos comandos procesados por el webhook de Meta.
        </p>

        <div class="overflow-x-auto">
            <table class="w-full min-w-[600px] text-left text-sm">
                <caption class="sr-only">Últimos comandos de WhatsApp y sus resultados</caption>
                <thead class="bg-muted text-foreground/75">
                    <tr>
                        <th scope="col" class="p-3">Fecha</th>
                        <th scope="col" class="p-3">Remitente</th>
                        <th scope="col" class="p-3">Comando</th>
                        <th scope="col" class="p-3">Resultado</th>
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
                            <td colspan="4" class="p-8 text-center text-foreground/75">
                                Todavía no se han recibido comandos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    {{-- COMANDOS --}}
    <section class="rounded-none border bg-card p-6 text-foreground shadow-storefront">
        <h2 class="mb-4 text-xl font-bold">
            Comandos disponibles
        </h2>

        <div class="space-y-3 text-sm">
            <div class="rounded-none bg-accent p-3">
                <code class="font-bold">ABRIR CHIFA</code>
                <p class="mt-1 text-foreground/75">
                    Habilita la recepción de nuevos pedidos.
                </p>
            </div>

            <div class="rounded-none bg-accent p-3">
                <code class="font-bold">CERRAR CHIFA</code>
                <p class="mt-1 text-foreground/75">
                    Deshabilita la recepción de nuevos pedidos.
                </p>
            </div>

            <div class="rounded-none bg-accent p-3">
                <code class="font-bold">ESTADO CHIFA</code>
                <p class="mt-1 text-foreground/75">
                    Consulta el estado. La respuesta automática
                    se habilitará al conectar el servicio de envío.
                </p>
            </div>
        </div>
    </section>
</div>
@endsection