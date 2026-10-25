<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Mis entregas | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 text-slate-900">
    <main class="max-w-2xl mx-auto p-4 space-y-5">

        <header class="bg-white rounded-2xl p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Mis entregas</h1>
                    <p class="text-sm text-slate-500">
                        Hola, {{ auth()->user()->name }}
                    </p>
                </div>

                <form method="POST" action="{{ route('delivery.logout') }}">
                    @csrf
                    <button class="text-sm text-red-600 font-semibold">
                        Salir
                    </button>
                </form>
            </div>
        </header>

        @forelse($orders as $order)
            <a href="{{ route('delivery.orders.show', $order) }}"
               class="block bg-white rounded-2xl p-5 shadow-sm space-y-3">

                <div class="flex justify-between items-center gap-3">
                    <strong>{{ $order->code }}</strong>

                    <span class="text-xs rounded-full bg-blue-100 text-blue-800 px-3 py-1">
                        {{ match($order->status) {
                            'confirmed' => 'Confirmado',
                            'preparing' => 'Preparando',
                            'on_the_way' => 'En camino',
                            default => $order->status
                        } }}
                    </span>
                </div>

                <p class="text-sm text-slate-600">
                    Cliente: {{ $order->customer_name }}
                </p>

                <p class="text-sm font-semibold text-blue-700">
                    Ver entrega →
                </p>
            </a>
        @empty
            <div class="bg-white rounded-2xl p-8 text-center">
                <p class="font-semibold">No tienes entregas pendientes.</p>
                <p class="text-sm text-slate-500 mt-2">
                    Los pedidos asignados aparecerán aquí.
                </p>
            </div>
        @endforelse

        <div>
            {{ $orders->links() }}
        </div>
    </main>
</body>
</html>
