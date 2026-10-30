@extends('layouts.admin')

@section('page-title', 'Platos')

@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Platos del menú</h1>
            <p class="text-sm text-foreground/75">Gestiona platos, precios y recomendaciones.</p>
        </div>
        <a href="{{ route('admin.products.create') }}"
           class="rounded-none bg-primary px-5 py-3 font-semibold text-primary-foreground">
            + Nuevo plato
        </a>
    </div>

    @if($errors->any())
        <div class="rounded-none bg-destructive/10 p-4 text-destructive">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($products as $product)
            <article class="overflow-hidden rounded-none border bg-card shadow-storefront">
                <x-catalog-image :item="$product" variant="card"
                    class="aspect-[4/3] w-full object-cover" />

                <div class="space-y-3 p-5">
                    <div class="flex items-start justify-between gap-2">
                        <div>
                            <h2 class="font-bold">{{ $product->name }}</h2>
                            <p class="text-xs text-foreground/75">
                                {{ $product->category?->name ?? 'Sin categoría' }}
                            </p>
                        </div>
                        <span class="text-lg font-bold text-primary">
                            S/ {{ number_format($product->price, 2) }}
                        </span>
                    </div>

                    <p class="text-sm text-foreground/75">
                        {{ \Illuminate\Support\Str::limit($product->description, 110) }}
                    </p>

                    <div class="flex flex-wrap gap-2 text-xs">
                        <span class="rounded-none px-3 py-1 {{ $product->is_available ? 'bg-green-100 text-green-700' : 'bg-destructive/10 text-destructive' }}">
                            {{ $product->is_available ? 'Disponible' : 'Agotado' }}
                        </span>
                        @if($product->is_featured)
                            <span class="rounded-none bg-amber-100 px-3 py-1 text-amber-700">
                                Destacado
                            </span>
                        @endif
                    </div>

                    <div class="flex flex-wrap gap-2 border-t pt-3">
                        <a href="{{ route('admin.products.edit', $product) }}"
                           class="rounded-none bg-primary px-3 py-2 text-sm text-primary-foreground">
                            Editar
                        </a>
                        <a href="{{ route('admin.products.recommendations.index', $product) }}"
                           class="rounded-none bg-accent px-3 py-2 text-sm text-primary">
                            Recomendaciones
                        </a>
                        <form method="POST"
                              action="{{ route('admin.products.destroy', $product) }}"
                              onsubmit="return confirm('¿Eliminar este plato?')">
                            @csrf
                            @method('DELETE')
                            <button class="rounded-none border border-destructive/30 px-3 py-2 text-sm text-destructive">
                                Eliminar
                            </button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="col-span-full rounded-none border p-8 text-center text-foreground/75">
                Todavía no hay platos.
            </p>
        @endforelse
    </div>

    {{ $products->links('pagination.storefront', ['label' => 'Paginación de platos']) }}
</div>
@endsection