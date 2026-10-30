<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Mis entregas | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="storefront-theme min-h-screen bg-background text-foreground">
    <main class="catalog-surface [overflow-wrap:anywhere] max-w-2xl mx-auto p-4 space-y-5">

        <header class="border border-border bg-card rounded-none p-5 shadow-storefront">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 class="text-2xl font-bold">Mis entregas</h1>
                    <p class="text-sm text-foreground/75">
                        Hola, {{ auth()->user()->name }}
                    </p>
                </div>

                <form method="POST" action="{{ route('delivery.logout') }}">
                    @csrf
                    <button class="min-h-11 px-3 text-sm text-destructive font-semibold">
                        Salir
                    </button>
                </form>
            </div>
        </header>

        @forelse($orders as $order)
            <a href="{{ route('delivery.orders.show', $order) }}"
               class="block border border-border hover:border-primary bg-card rounded-none p-5 shadow-storefront space-y-3">

                <div class="flex flex-wrap justify-between items-center gap-3">
                    <strong>{{ $order->code }}</strong>

                    <span class="text-xs rounded-none bg-accent text-accent-foreground px-3 py-1">
                        {{ match($order->status) {
                            'confirmed' => 'Confirmado',
                            'preparing' => 'Preparando',
                            'on_the_way' => 'En camino',
                            default => $order->status
                        } }}
                    </span>
                </div>

                <p class="text-sm text-foreground/75">
                    Cliente: {{ $order->customer_name }}
                </p>

                <p class="text-sm font-semibold text-primary">
                    Ver entrega →
                </p>
            </a>
        @empty
            <div class="border border-border bg-card rounded-none p-8 text-center">
                <p class="font-semibold">No tienes entregas pendientes.</p>
                <p class="text-sm text-foreground/75 mt-2">
                    Los pedidos asignados aparecerán aquí.
                </p>
            </div>
        @endforelse

        <div>
            {{ $orders->links('pagination.storefront', ['label' => 'Paginación de entregas']) }}
        </div>
    </main>
</body>
</html>
