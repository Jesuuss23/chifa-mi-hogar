@extends('layouts.admin')

@section('page-title', 'Categorías de adicionales')

@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Categorías de adicionales</h1>
            <p class="text-sm text-foreground/75">
                Bebidas, entradas, acompañamientos y salsas.
            </p>
        </div>
        <a href="{{ route('admin.extra-categories.create') }}"
           class="rounded-none bg-primary px-5 py-3 font-semibold text-primary-foreground">
            + Nueva categoría
        </a>
    </div>
    @if($errors->any())
        <div class="rounded-none bg-destructive/10 p-4 text-destructive">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($categories as $category)
            <article class="overflow-hidden rounded-none border bg-card shadow-storefront">
                <x-catalog-image :item="$category" variant="card"
                    class="aspect-[16/9] w-full object-cover" />

                <div class="space-y-3 p-5">
                    <h2 class="text-lg font-bold">{{ $category->name }}</h2>
                    <p class="text-sm text-foreground/75">
                        {{ $category->description ?: 'Sin descripción' }}
                    </p>
                    <p class="text-sm">{{ $category->extras_count }} adicionales</p>

                    <span class="inline-block rounded-none px-3 py-1 text-xs {{ $category->is_active ? 'bg-green-100 text-green-700' : 'bg-muted text-foreground/75' }}">
                        {{ $category->is_active ? 'Activa' : 'Inactiva' }}
                    </span>

                    <div class="flex flex-wrap gap-2 border-t pt-3">
                        <a href="{{ route('admin.extra-categories.edit', $category) }}"
                           class="rounded-none bg-primary px-4 py-2 text-sm text-primary-foreground">
                            Editar
                        </a>
                        <form method="POST"
                              action="{{ route('admin.extra-categories.destroy', $category) }}"
                              onsubmit="return confirm('¿Eliminar esta categoría?')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-none border border-destructive/30 px-4 py-2 text-sm text-destructive">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-none border p-8 text-center text-foreground/75">
                No hay categorías de adicionales.
            </p>
        @endforelse
    </div>

    {{ $categories->links('pagination.storefront', ['label' => 'Paginación de categorías de adicionales']) }}
</div>
@endsection