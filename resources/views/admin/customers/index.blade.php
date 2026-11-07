@extends('layouts.admin')
@section('title', 'Clientes')
@section('page-title', 'Clientes registrados')
@section('content')
<div data-customer-directory>
    <p class="text-sm text-foreground/75">Consulta las cuentas de clientes y sus datos guardados. Las compras sin cuenta no aparecen en este listado.</p>
    <form method="GET" action="{{ route('admin.customers.index') }}" class="mt-5 flex flex-wrap items-end gap-3">
        <div class="min-w-0 flex-1">
            <label for="customer-search" class="block text-sm font-semibold">Nombre, correo o teléfono</label>
            <input id="customer-search" name="q" type="search" value="{{ $search }}" maxlength="100"
                   class="mt-2 w-full rounded-lg border border-input bg-card p-3" placeholder="Buscar cliente">
        </div>
        <button class="rounded-lg bg-primary px-5 py-3 text-primary-foreground" type="submit">Buscar</button>
        <a href="{{ route('admin.customers.index') }}" class="px-3 py-3 underline">Limpiar</a>
    </form>
    @error('q')<p role="alert" class="mt-3 text-destructive">{{ $message }}</p>@enderror
    <p class="my-4 text-sm" role="status">{{ $customers->total() }} {{ $search !== '' ? 'coincidencias' : 'clientes registrados' }}</p>
    <div class="overflow-x-auto rounded-xl border border-border bg-card">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Clientes registrados, más recientes primero</caption>
            <thead class="bg-muted"><tr>
                <th scope="col" class="p-4">Cliente</th><th scope="col" class="p-4">Teléfono</th>
                <th scope="col" class="p-4">Registro ({{ config('app.timezone') }})</th><th scope="col" class="p-4">Detalle</th>
            </tr></thead>
            <tbody>
                @forelse($customers as $customer)
                <tr class="border-t border-border">
                    <td class="p-4"><strong class="block break-words">{{ $customer->name }}</strong><span class="break-all text-foreground/75">{{ $customer->email }}</span></td>
                    <td class="p-4">{{ $customer->customerProfile?->phone ?: 'No indicado' }}</td>
                    <td class="whitespace-nowrap p-4">{{ $customer->created_at?->timezone(config('app.timezone'))->format('d/m/Y H:i') ?? 'No disponible' }}</td>
                    <td class="p-4"><a data-customer-open href="{{ route('admin.customers.show', $customer->id) }}" class="inline-block whitespace-nowrap rounded-lg border border-border px-4 py-2 underline"
                        aria-label="Ver detalles de {{ $customer->name }}">Ver detalles</a></td>
                </tr>
                @empty
                <tr><td colspan="4" class="p-8 text-center">No se encontraron clientes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5">{{ $customers->links() }}</div>
    <dialog class="customer-detail-dialog" aria-labelledby="customer-modal-title">
        <div class="customer-detail-toolbar">
            <h2 id="customer-modal-title" class="text-lg font-semibold">Ficha del cliente</h2>
            <button type="button" data-customer-close class="rounded-lg border border-border px-4 py-2" autofocus>Cerrar</button>
        </div>
        <div data-customer-status role="status" class="px-5 pb-3"></div>
        <div data-customer-content class="px-5 pb-5"></div>
        <div class="border-t border-border p-5"><a data-customer-page class="underline">Abrir ficha en una página</a></div>
    </dialog>
</div>
@endsection
