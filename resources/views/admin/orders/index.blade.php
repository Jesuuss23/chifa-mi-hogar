@extends('layouts.admin')

@section('page-title', 'Pedidos')

@section('content')
<div class="catalog-surface admin-orders-surface max-w-7xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-foreground">Gestión de pedidos</h1>
        <p class="text-sm text-foreground/75 mt-1">
            Consulta, verifica y administra los pedidos de Chifa Mi Hogar.
        </p>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach($statuses as $key => $label)
            <a href="{{ route('admin.orders.index', ['status' => $key]) }}"
               class="rounded-none border bg-card p-4 hover:border-primary
                      {{ request('status') === $key ? 'border-primary' : 'border-border' }}">
                <div class="text-xs text-foreground/75">{{ $label }}</div>
                <div class="text-2xl font-bold mt-1">
                    {{ $counts[$key] ?? 0 }}
                </div>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.orders.index') }}"
          class="rounded-none bg-card border border-border p-4 flex flex-wrap gap-3">
        <input
            type="text"
            name="search"
            aria-label="Buscar por código, cliente o teléfono"
            value="{{ request('search') }}"
            placeholder="Código, cliente o teléfono"
            class="flex-1 min-w-52 rounded-none border-input"
        >

        <select aria-label="Filtrar por estado" name="status" class="rounded-none border-input">
            <option value="">Todos los estados</option>
            @foreach($statuses as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <button type="submit"
                class="bg-primary text-primary-foreground rounded-none px-5 py-2">
            Buscar
        </button>

        <a href="{{ route('admin.orders.index') }}"
           class="rounded-none border px-5 py-2">
            Limpiar
        </a>
    </form>

    <div class="bg-card border border-border rounded-none overflow-x-auto">
        <table class="min-w-full text-sm">
            <caption class="sr-only">Pedidos y estado de sus pagos</caption>
            <thead class="bg-muted text-foreground/75">
                <tr>
                    <th scope="col" class="text-left px-4 py-3">Pedido</th>
                    <th scope="col" class="text-left px-4 py-3">Cliente</th>
                    <th scope="col" class="text-left px-4 py-3">Fecha</th>
                    <th scope="col" class="text-left px-4 py-3">Estado</th>
                    <th scope="col" class="text-left px-4 py-3">Pago</th>
                    <th scope="col" class="text-right px-4 py-3">Total</th>
                    <th scope="col" class="text-right px-4 py-3">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($orders as $order)
                    <tr class="hover:bg-muted">
                        <td class="px-4 py-4 font-semibold">
                            {{ $order->code }}
                            <div class="text-xs text-muted-foreground">
                                {{ $order->items_count }} ítems
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            {{ $order->customer_name }}
                            <div class="text-xs text-foreground/75">
                                {{ $order->customer_phone }}
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            {{ $order->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-4">
                            <span class="rounded-none bg-accent px-3 py-1">
                                {{ $statuses[$order->status] ?? $order->status }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            @if($order->payment_status === 'verified')
                                <span class="text-green-700 font-semibold">Verificado</span>
                            @elseif($order->payment_status === 'rejected')
                                <span class="text-destructive">Rechazado</span>
                            @else
                                <span class="text-amber-700">Pendiente</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right font-bold">
                            S/ {{ number_format((float) $order->total, 2) }}
                        </td>
                        <td class="px-4 py-4 text-right">
                            <a href="{{ route('admin.orders.show', $order) }}"
                               class="text-primary font-semibold hover:underline">
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-16 text-foreground/75">
                            Todavía no hay pedidos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $orders->links('pagination.storefront', ['label' => 'Paginación de pedidos']) }}
</div>
@endsection