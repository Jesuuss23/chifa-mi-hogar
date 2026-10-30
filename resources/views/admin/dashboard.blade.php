@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Resumen del negocio')

@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-7xl space-y-8">
    <div>
        <h1 class="text-2xl font-bold">Resumen del negocio</h1>
        <p class="mt-1 text-sm text-foreground/75">
            Los indicadores de hoy usan la hora de Lima.
        </p>
    </div>

    <dl class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ([
            ['title' => 'Pedidos de hoy', 'value' => $ordersToday, 'unit' => '',
             'help' => 'Creados hoy, incluidos los cancelados.'],
            ['title' => 'Ventas completadas hoy', 'value' => number_format($salesToday, 2), 'unit' => 'S/ ',
             'help' => 'Entregados hoy y con pago verificado. Incluye delivery.'],
            ['title' => 'Pedidos pendientes', 'value' => $pendingOrders, 'unit' => '',
             'help' => 'En estado pendiente, de cualquier fecha.'],
            ['title' => 'Estado de cocina', 'value' => $kitchenOpen ? 'Abierta' : 'Cerrada', 'unit' => '',
             'help' => $kitchenOpen ? 'Recibiendo pedidos.' : 'No se reciben nuevos pedidos.'],
        ] as $card)
            <div class="rounded-none border border-border bg-card p-6 shadow-storefront">
                <dt class="text-sm text-foreground/75">{{ $card['title'] }}</dt>
                <dd class="mt-3 text-2xl font-bold">{{ $card['unit'] }}{{ $card['value'] }}</dd>
                <dd class="mt-2 text-xs text-foreground/75">{{ $card['help'] }}</dd>
            </div>
        @endforeach
    </dl>

    <section aria-labelledby="recent-orders-title" class="rounded-none border border-border bg-card shadow-storefront">
        <div class="flex flex-wrap items-center justify-between gap-3 p-6">
            <h2 id="recent-orders-title" class="text-lg font-bold">Últimos pedidos</h2>
            <a href="{{ route('admin.orders.index') }}" class="font-semibold text-primary hover:underline">
                Ver todos los pedidos
            </a>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[650px] text-left text-sm">
                <caption class="sr-only">Los cinco pedidos más recientes, con fechas en hora de Lima</caption>
                <thead class="bg-muted text-foreground/75">
                    <tr>
                        <th scope="col" class="p-4">Pedido</th>
                        <th scope="col" class="p-4">Cliente</th>
                        <th scope="col" class="p-4">Fecha (Lima)</th>
                        <th scope="col" class="p-4">Estado</th>
                        <th scope="col" class="p-4 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($recentOrders as $order)
                        <tr>
                            <td class="p-4">
                                <a href="{{ route('admin.orders.show', $order) }}" class="font-semibold text-primary hover:underline">
                                    {{ $order->code }}
                                </a>
                            </td>
                            <td class="p-4">{{ $order->customer_name }}</td>
                            <td class="p-4">{{ $order->created_at->copy()->timezone('America/Lima')->format('d/m/Y H:i') }}</td>
                            <td class="p-4">{{ $statuses[$order->status] ?? $order->status }}</td>
                            <td class="p-4 text-right font-semibold">S/ {{ number_format((float) $order->total, 2) }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="p-8 text-center text-foreground/75">
                                Todavía no hay pedidos registrados.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
@endsection
