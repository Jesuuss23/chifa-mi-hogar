@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Categorías de adicionales</h1>
            <p class="text-sm text-slate-500">
                Bebidas, entradas, acompañamientos y salsas.
            </p>
        </div>
        <a href="{{ route('admin.extra-categories.create') }}"
           class="rounded-xl bg-orange-600 px-5 py-3 font-semibold text-white">
            + Nueva categoría
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-green-50 p-4 text-green-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($categories as $category)
            <article class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                <x-catalog-image :item="$category" variant="card"
                    class="aspect-[16/9] w-full object-cover" />

                <div class="space-y-3 p-5">
                    <h2 class="text-lg font-bold">{{ $category->name }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ $category->description ?: 'Sin descripción' }}
                    </p>
                    <p class="text-sm">{{ $category->extras_count }} adicionales</p>

                    <span class="inline-block rounded-full px-3 py-1 text-xs {{ $category->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $category->is_active ? 'Activa' : 'Inactiva' }}
                    </span>

                    <div class="flex gap-2 border-t pt-3">
                        <a href="{{ route('admin.extra-categories.edit', $category) }}"
                           class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">
                            Editar
                        </a>
                        <form method="POST"
                              action="{{ route('admin.extra-categories.destroy', $category) }}"
                              onsubmit="return confirm('¿Eliminar esta categoría?')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg border border-red-200 px-4 py-2 text-sm text-red-600">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-xl border p-8 text-center text-slate-500">
                No hay categorías de adicionales.
            </p>
        @endforelse
    </div>

    {{ $categories->links() }}
</div>
@endsection