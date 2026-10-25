@extends('layouts.admin')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold text-gray-900">Gestión de pedidos</h1>
        <p class="text-sm text-gray-500 mt-1">
            Consulta, verifica y administra los pedidos de Chifa Mi Hogar.
        </p>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-green-50 p-4 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
        @foreach($statuses as $key => $label)
            <a href="{{ route('admin.orders.index', ['status' => $key]) }}"
               class="rounded-xl border bg-white p-4 hover:border-red-400
                      {{ request('status') === $key ? 'border-red-500' : 'border-gray-200' }}">
                <div class="text-xs text-gray-500">{{ $label }}</div>
                <div class="text-2xl font-bold mt-1">
                    {{ $counts[$key] ?? 0 }}
                </div>
            </a>
        @endforeach
    </div>

    <form method="GET" action="{{ route('admin.orders.index') }}"
          class="rounded-xl bg-white border border-gray-200 p-4 flex flex-wrap gap-3">
        <input
            type="text"
            name="search"
            value="{{ request('search') }}"
            placeholder="Código, cliente o teléfono"
            class="flex-1 min-w-52 rounded-lg border-gray-300"
        >

        <select name="status" class="rounded-lg border-gray-300">
            <option value="">Todos los estados</option>
            @foreach($statuses as $key => $label)
                <option value="{{ $key }}" @selected(request('status') === $key)>
                    {{ $label }}
                </option>
            @endforeach
        </select>

        <button type="submit"
                class="bg-red-600 text-white rounded-lg px-5 py-2">
            Buscar
        </button>

        <a href="{{ route('admin.orders.index') }}"
           class="rounded-lg border px-5 py-2">
            Limpiar
        </a>
    </form>

    <div class="bg-white border border-gray-200 rounded-xl overflow-x-auto">
        <table class="min-w-full text-sm">
            <thead class="bg-gray-50 text-gray-600">
                <tr>
                    <th class="text-left px-4 py-3">Pedido</th>
                    <th class="text-left px-4 py-3">Cliente</th>
                    <th class="text-left px-4 py-3">Fecha</th>
                    <th class="text-left px-4 py-3">Estado</th>
                    <th class="text-left px-4 py-3">Pago</th>
                    <th class="text-right px-4 py-3">Total</th>
                    <th class="text-right px-4 py-3">Acción</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($orders as $order)
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-4 font-semibold">
                            {{ $order->code }}
                            <div class="text-xs text-gray-400">
                                {{ $order->items_count }} ítems
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            {{ $order->customer_name }}
                            <div class="text-xs text-gray-500">
                                {{ $order->customer_phone }}
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            {{ $order->created_at->format('d/m/Y H:i') }}
                        </td>
                        <td class="px-4 py-4">
                            <span class="rounded-full bg-gray-100 px-3 py-1">
                                {{ $statuses[$order->status] ?? $order->status }}
                            </span>
                        </td>
                        <td class="px-4 py-4">
                            @if($order->payment_status === 'verified')
                                <span class="text-green-700 font-semibold">Verificado</span>
                            @elseif($order->payment_status === 'rejected')
                                <span class="text-red-700">Rechazado</span>
                            @else
                                <span class="text-amber-700">Pendiente</span>
                            @endif
                        </td>
                        <td class="px-4 py-4 text-right font-bold">
                            S/ {{ number_format((float) $order->total, 2) }}
                        </td>
                        <td class="px-4 py-4 text-right">
                            <a href="{{ route('admin.orders.show', $order) }}"
                               class="text-red-600 font-semibold hover:underline">
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="text-center py-16 text-gray-500">
                            Todavía no hay pedidos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $orders->links() }}
</div>
@endsection