@extends('layouts.admin')

@section('page-title', 'Adicionales')

@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Adicionales</h1>
            <p class="text-sm text-foreground/75">
                Complementos que se ofrecen junto con los platos.
            </p>
        </div>
        <a href="{{ route('admin.extras.create') }}"
           class="rounded-none bg-primary px-5 py-3 font-semibold text-primary-foreground">
            + Nuevo adicional
        </a>
    </div>
    @if($errors->any())
        <div class="rounded-none bg-destructive/10 p-4 text-destructive">{{ $errors->first() }}</div>
    @endif

    <div class="overflow-x-auto rounded-none border bg-card shadow-storefront">
        <table class="w-full min-w-[750px] text-left text-sm">
            <caption class="sr-only">Adicionales, precios y disponibilidad</caption>
            <thead class="bg-muted text-foreground/75">
                <tr>
                    <th scope="col" class="p-4">Imagen</th>
                    <th scope="col" class="p-4">Adicional</th>
                    <th scope="col" class="p-4">Categoría</th>
                    <th scope="col" class="p-4">Precio</th>
                    <th scope="col" class="p-4">Estado</th>
                    <th scope="col" class="p-4">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($extras as $extra)
                    <tr>
                        <td class="p-4">
                            <x-catalog-image :item="$extra" variant="thumbnail"
                                class="h-16 w-16 rounded-none object-cover" />
                        </td>
                        <td class="p-4">
                            <div class="font-semibold">{{ $extra->name }}</div>
                            <div class="text-xs text-foreground/75">
                                {{ \Illuminate\Support\Str::limit($extra->description, 60) }}
                            </div>
                        </td>
                        <td class="p-4">
                            {{ $extra->extraCategory?->name ?? 'Sin categoría' }}
                        </td>
                        <td class="p-4 font-semibold">
                            S/ {{ number_format($extra->price, 2) }}
                        </td>
                        <td class="p-4">
                            <span class="rounded-none px-3 py-1 text-xs {{ $extra->is_available ? 'bg-green-100 text-green-700' : 'bg-destructive/10 text-destructive' }}">
                                {{ $extra->is_available ? 'Disponible' : 'No disponible' }}
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex flex-wrap gap-2">
                                <a href="{{ route('admin.extras.edit', $extra) }}"
                                   class="rounded-none bg-primary px-3 py-2 text-primary-foreground">
                                    Editar
                                </a>
                                <form method="POST"
                                      action="{{ route('admin.extras.destroy', $extra) }}"
                                      onsubmit="return confirm('¿Eliminar este adicional?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-none border border-destructive/30 px-3 py-2 text-destructive">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-10 text-center text-foreground/75">
                            No hay adicionales registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $extras->links('pagination.storefront', ['label' => 'Paginación de adicionales']) }}
</div>
@endsection