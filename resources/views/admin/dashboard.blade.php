@extends('layouts.admin')

@section('title', 'Dashboard')
@section('page-title', 'Resumen del negocio')

@section('content')

<div class="mb-8">
    <h1 class="text-2xl font-bold">
        Dashboard
    </h1>
    <p class="mt-1 text-sm text-gray-500">
        Bienvenido al centro de control de Chifa Mi Hogar.
    </p>
</div>

<div class="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">

    @foreach ([
        [
            'title' => 'Pedidos de hoy',
            'value' => $ordersToday,
            'unit' => ''
        ],
        [
            'title' => 'Ventas completadas',
            'value' => number_format($salesToday, 2),
            'unit' => 'S/ '
        ],
        [
            'title' => 'Pedidos pendientes',
            'value' => $pendingOrders,
            'unit' => ''
        ],
        [
            'title' => 'Estado de cocina',
            'value' => $kitchenOpen ? 'Abierta' : 'Cerrada',
            'unit' => ''
        ]
    ] as $card)

        <div class="rounded-xl border border-gray-200
                    bg-white p-6 shadow-sm">

            <p class="text-sm text-gray-500">
                {{ $card['title'] }}
            </p>

            <h3 class="mt-3 text-2xl font-bold">
                {{ $card['unit'] }}{{ $card['value'] }}
            </h3>

        </div>

    @endforeach

</div>

<div class="mt-8 rounded-xl border border-gray-200
            bg-white p-6 shadow-sm">

    <h2 class="text-lg font-bold">
        Últimos pedidos
    </h2>

    <p class="mt-4 text-sm text-gray-500">
        Todavía no hay pedidos para mostrar.
        Esta sección se conectará a PostgreSQL
        cuando implementemos el módulo de pedidos.
    </p>

</div>

@endsection