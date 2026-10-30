@extends('layouts.admin')

@section('page-title', 'Recomendaciones')

@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-7xl space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.products.index') }}"
               class="text-sm text-primary">← Volver a platos</a>
            <h1 class="mt-2 text-2xl font-bold">Recomendaciones</h1>
            <p class="text-sm text-foreground/75">Plato: {{ $product->name }}</p>
        </div>
        <a href="{{ route('admin.products.recommendations.create', $product) }}"
           class="rounded-none bg-primary px-5 py-3 font-semibold text-primary-foreground">
            + Nueva recomendación
        </a>
    </div>
    @if($errors->any())
        <div class="rounded-none bg-destructive/10 p-4 text-destructive">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($recommendations as $recommendation)
            <article class="overflow-hidden rounded-none border bg-card shadow-storefront">
                <x-catalog-image :item="$recommendation" variant="card"
                    class="aspect-[4/3] w-full object-cover" />

                <div class="space-y-3 p-5">
                    <h2 class="font-bold">{{ $recommendation->name }}</h2>
                    <p class="text-sm text-foreground/75">
                        {{ $recommendation->description }}
                    </p>

                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase text-foreground/75">
                            Incluye
                        </p>
                        @foreach($recommendation->extras as $extra)
                            <p class="text-sm">
                                • {{ $extra->name }}
                                @if(($extra->pivot->quantity ?? 1) > 1)
                                    × {{ $extra->pivot->quantity }}
                                @endif
                            </p>
                        @endforeach
                    </div>

                    <div class="border-t pt-3">
                        <p class="text-xs text-foreground/75">Precio calculado</p>
                        <p class="text-xl font-bold text-primary">
                            S/ {{ number_format(
                                $product->price + $recommendation->extras->sum(
                                    fn ($extra) => $extra->price * ($extra->pivot->quantity ?? 1)
                                ), 2
                            ) }}
                        </p>
                    </div>

                    <span class="inline-block rounded-none px-3 py-1 text-xs {{ $recommendation->is_active ? 'bg-green-100 text-green-700' : 'bg-muted text-foreground/75' }}">
                        {{ $recommendation->is_active ? 'Activa' : 'Inactiva' }}
                    </span>

                    <div class="flex flex-wrap gap-2 border-t pt-3">
                        <a href="{{ route('admin.products.recommendations.edit', [$product, $recommendation]) }}"
                           class="rounded-none bg-primary px-4 py-2 text-sm text-primary-foreground">
                            Editar
                        </a>
                        <form method="POST"
                              action="{{ route('admin.products.recommendations.destroy', [$product, $recommendation]) }}"
                              onsubmit="return confirm('¿Eliminar esta recomendación?')">
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
                Este plato todavía no tiene recomendaciones.
            </p>
        @endforelse
    </div>

    {{ $recommendations->links('pagination.storefront', ['label' => 'Paginación de recomendaciones']) }}
</div>
@endsection