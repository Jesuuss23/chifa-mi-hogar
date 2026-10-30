@extends('layouts.admin')

@section('title', 'Valoraciones de pedidos')
@section('page-title', 'Valoraciones de pedidos')

@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-6xl">

    <a href="{{ route('admin.dashboard') }}"
       class="text-primary">
        ← Volver al panel
    </a>

    <h1 class="mt-5 text-3xl font-bold">
        Valoraciones de clientes
    </h1>

    <p class="mt-2 text-foreground/75">
        Estadísticas de compras verificadas.
        Las puntuaciones no pueden editarse desde este panel.
    </p>

    <div class="mt-6 grid gap-4 sm:grid-cols-3">
        <div class="rounded-none border bg-card p-5 shadow-storefront">
            <p class="text-sm text-foreground/75">Promedio</p>
            <p class="mt-2 text-3xl font-bold">
                {{ number_format($average, 2) }} / 5
            </p>
        </div>

        <div class="rounded-none border bg-card p-5 shadow-storefront">
            <p class="text-sm text-foreground/75">Valoraciones</p>
            <p class="mt-2 text-3xl font-bold">{{ $total }}</p>
        </div>

        <div class="rounded-none border bg-card p-5 shadow-storefront">
            <p class="text-sm text-foreground/75">Clientes</p>
            <p class="mt-2 text-3xl font-bold">{{ $customers }}</p>
        </div>
    </div>

    <div class="mt-6 rounded-none border bg-card p-6 shadow-storefront">
        <h2 class="text-xl font-bold">Distribución de estrellas</h2>

        @for ($star = 5; $star >= 1; $star--)
            @php
                $count = (int) ($distribution[$star] ?? 0);
                $percent = $total > 0 ? ($count / $total) * 100 : 0;
            @endphp

            <div class="mt-4 flex items-center gap-3">
                <span class="w-10">{{ $star }} ★</span>

                <div class="h-3 flex-1 overflow-hidden rounded-none bg-muted">
                    <div
                        class="h-full rounded-none bg-amber-400"
                        style="width: {{ $percent }}%"
                    ></div>
                </div>

                <span class="w-10 text-right">{{ $count }}</span>
            </div>
        @endfor
    </div>

    <div class="mt-6 overflow-x-auto rounded-none border bg-card p-6 shadow-storefront">
        <h2 class="mb-4 text-xl font-bold">Historial de valoraciones</h2>

        <table class="w-full min-w-[700px] text-left text-sm">
            <caption class="sr-only">Valoraciones de pedidos, comentarios y fechas</caption>
            <thead class="bg-muted">
                <tr class="border-b">
                    <th scope="col" class="p-3">Cliente</th>
                    <th scope="col" class="p-3">Pedido</th>
                    <th scope="col" class="p-3">Estrellas</th>
                    <th scope="col" class="p-3">Comentario</th>
                    <th scope="col" class="p-3">Fecha</th>
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
                        <td colspan="5" class="p-6 text-center text-foreground/75">
                            Todavía no hay valoraciones.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="mt-5">
            {{ $ratings->links('pagination.storefront', ['label' => 'Paginación de valoraciones']) }}
        </div>
    </div>
</div>

@endsection
