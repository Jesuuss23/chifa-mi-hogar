@extends('layouts.admin')

@section('title', 'Combos')
@section('page-title', 'Combos')

@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-6xl">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.dashboard') }}"
               class="text-sm text-primary">
                ← Volver al panel
            </a>

            <h1 class="mt-3 text-3xl font-bold">Gestión de combos</h1>
            <p class="text-foreground/75">
                Crea ofertas utilizando platos y adicionales existentes.
            </p>
        </div>

        <a href="{{ route('admin.combos.create') }}"
           class="rounded-none bg-primary px-5 py-3 font-semibold text-primary-foreground">
            + Crear combo
        </a>
    </div>

    <div class="mt-6 overflow-x-auto rounded-none border bg-card shadow-storefront">
        <table class="w-full min-w-[650px] text-left text-sm">
            <caption class="sr-only">Combos, precios y publicación en el catálogo</caption>
            <thead class="bg-muted">
                <tr>
                    <th scope="col" class="p-4">Combo</th>
                    <th scope="col" class="p-4">Referencial</th>
                    <th scope="col" class="p-4">Venta</th>
                    <th scope="col" class="p-4">En carta</th>
                    <th scope="col" class="p-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($combos as $combo)
                    <tr class="border-t">
                        <td class="p-4">
                            <p class="font-semibold">{{ $combo->name }}</p>
                            <p class="text-xs text-foreground/75">
                                {{ $combo->items->count() }} componentes
                            </p>
                        </td>

                        <td class="p-4">
                            S/ {{ number_format($combo->reference_price, 2) }}
                        </td>

                        <td class="p-4 font-semibold">
                            S/ {{ number_format((float) $combo->price, 2) }}
                        </td>

                        <td class="p-4">
                            {{ $combo->show_in_menu ? 'Sí' : 'No' }}
                        </td>

                        <td class="p-4">
                            <a href="{{ route('admin.combos.edit', $combo) }}"
                               class="font-medium text-primary">
                                Editar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-foreground/75">
                            Todavía no hay combos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">
        {{ $combos->links('pagination.storefront', ['label' => 'Paginación de combos']) }}
    </div>
</div>

@endsection
