<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Valoraciones | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 p-6">

<main class="mx-auto max-w-6xl">

    <a href="{{ route('admin.dashboard') }}"
       class="text-red-700">
        ← Volver al panel
    </a>

    <h1 class="mt-5 text-3xl font-bold">
        Valoraciones de clientes
    </h1>

    <p class="mt-2 text-slate-500">
        Estadísticas de compras verificadas.
        Las puntuaciones no pueden editarse desde este panel.
    </p>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Promedio</p>
            <p class="mt-2 text-3xl font-bold">
                {{ number_format($average, 2) }} / 5
            </p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Valoraciones</p>
            <p class="mt-2 text-3xl font-bold">{{ $total }}</p>
        </div>

        <div class="rounded-xl bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Clientes</p>
            <p class="mt-2 text-3xl font-bold">{{ $customers }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white p-6 shadow-sm">
        <h2 class="text-xl font-bold">Distribución de estrellas</h2>

        @for ($star = 5; $star >= 1; $star--)
            @php
                $count = (int) ($distribution[$star] ?? 0);
                $percent = $total > 0 ? ($count / $total) * 100 : 0;
            @endphp

            <div class="mt-4 flex items-center gap-3">
                <span class="w-10">{{ $star }} ★</span>

                <div class="h-3 flex-1 overflow-hidden rounded-full bg-slate-100">
                    <div
                        class="h-full rounded-full bg-amber-400"
                        style="width: {{ $percent }}%"
                    ></div>
                </div>

                <span class="w-10 text-right">{{ $count }}</span>
            </div>
        @endfor
    </div>

    <div class="mt-6 overflow-x-auto rounded-xl bg-white p-6 shadow-sm">
        <h2 class="mb-4 text-xl font-bold">Historial de valoraciones</h2>

        <table class="w-full text-left text-sm">
            <thead>
                <tr class="border-b">
                    <th class="p-3">Cliente</th>
                    <th class="p-3">Pedido</th>
                    <th class="p-3">Estrellas</th>
                    <th class="p-3">Comentario</th>
                    <th class="p-3">Fecha</th>
                </tr>
            </thead>

            <tbody>
                @forelse ($ratings as $rating)
                    <tr class="border-b">
                        <td class="p-3">
                            {{ $rating->user?->name ?? 'Usuario eliminado' }}
                        </td>

                        <td class="p-3">
                            {{ $rating->order?->code ?? 'Pedido eliminado' }}
                        </td>

                        <td class="p-3 font-semibold text-amber-600">
                            {{ $rating->rating }} / 5
                        </td>

                        <td class="p-3">
                            {{ $rating->comment ?: 'Sin comentario' }}
                        </td>

                        <td class="p-3">
                            {{ $rating->created_at->format('d/m/Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-6 text-center text-slate-500">
                            Todavía no hay valoraciones.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-5">
            {{ $ratings->links() }}
        </div>
    </div>
</main>

</body>
</html>
