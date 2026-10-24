@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Platos del menú</h1>
            <p class="text-sm text-slate-500">Gestiona platos, precios y recomendaciones.</p>
        </div>
        <a href="{{ route('admin.products.create') }}"
           class="rounded-xl bg-orange-600 px-5 py-3 font-semibold text-white">
            + Nuevo plato
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-green-50 p-4 text-green-700">{{ session('success') }}</div>
    @endif

    @if($errors->any())
        <div class="rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($products as $product)
            <article class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                <x-catalog-image :item="$product" variant="card"
                    class="aspect-[4/3] w-full object-cover" />

                <div class="space-y-3 p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="font-bold">{{ $product->name }}</h2>
                            <p class="text-xs text-slate-500">
                                {{ $product->category?->name ?? 'Sin categoría' }}
                            </p>
                        </div>
                        <span class="text-lg font-bold text-orange-600">
                            S/ {{ number_format($product->price, 2) }}
                        </span>
                    </div>

                    <p class="text-sm text-slate-500">
                        {{ \Illuminate\Support\Str::limit($product->description, 110) }}
                    </p>

                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-full px-3 py-1 {{ $product->is_available ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                            {{ $product->is_available ? 'Disponible' : 'Agotado' }}
                        </span>
                        @if($product->is_featured)
                            <span class="rounded-full bg-amber-100 px-3 py-1 text-amber-700">
                                Destacado
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2 border-t pt-3">
                        <a href="{{ route('admin.products.edit', $product) }}"
                           class="rounded-lg bg-slate-900 px-3 py-2 text-sm text-white">
                            Editar
                        </a>
                        <a href="{{ route('admin.products.recommendations.index', $product) }}"
                           class="rounded-lg bg-orange-100 px-3 py-2 text-sm text-orange-700">
                            Recomendaciones
                        </a>
                        <form method="POST"
                              action="{{ route('admin.products.destroy', $product) }}"
                              onsubmit="return confirm('¿Eliminar este plato?')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-lg border border-red-200 px-3 py-2 text-sm text-red-600">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-xl border p-8 text-center text-slate-500">
                Todavía no hay platos.
            </p>
        @endforelse
    </div>

    {{ $products->links() }}
</div>
@endsection