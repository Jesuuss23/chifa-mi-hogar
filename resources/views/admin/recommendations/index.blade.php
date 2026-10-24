@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.products.index') }}"
               class="text-sm text-orange-600">← Volver a platos</a>
            <h1 class="mt-2 text-2xl font-bold">Recomendaciones</h1>
            <p class="text-sm text-slate-500">Plato: {{ $product->name }}</p>
        </div>
        <a href="{{ route('admin.products.recommendations.create', $product) }}"
           class="rounded-xl bg-orange-600 px-5 py-3 font-semibold text-white">
            + Nueva recomendación
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-green-50 p-4 text-green-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse($recommendations as $recommendation)
            <article class="overflow-hidden rounded-2xl border bg-white shadow-sm">
                <x-catalog-image :item="$recommendation" variant="card"
                    class="aspect-[4/3] w-full object-cover" />

                <div class="space-y-3 p-5">
                    <h2 class="font-bold">{{ $recommendation->name }}</h2>
                    <p class="text-sm text-slate-500">
                        {{ $recommendation->description }}
                    </p>

                    <div class="space-y-1">
                        <p class="text-xs font-semibold uppercase text-slate-500">
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
                        <p class="text-xs text-slate-500">Precio calculado</p>
                        <p class="text-xl font-bold text-orange-600">
                            S/ {{ number_format(
                                $product->price + $recommendation->extras->sum(
                                    fn ($extra) => $extra->price * ($extra->pivot->quantity ?? 1)
                                ), 2
                            ) }}
                        </p>
                    </div>

                    <span class="inline-block rounded-full px-3 py-1 text-xs {{ $recommendation->is_active ? 'bg-green-100 text-green-700' : 'bg-slate-100 text-slate-600' }}">
                        {{ $recommendation->is_active ? 'Activa' : 'Inactiva' }}
                    </span>

                    <div class="flex gap-2 border-t pt-3">
                        <a href="{{ route('admin.products.recommendations.edit', [$product, $recommendation]) }}"
                           class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">
                            Editar
                        </a>
                        <form method="POST"
                              action="{{ route('admin.products.recommendations.destroy', [$product, $recommendation]) }}"
                              onsubmit="return confirm('¿Eliminar esta recomendación?')">
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
                Este plato todavía no tiene recomendaciones.
            </p>
        @endforelse
    </div>

    {{ $recommendations->links() }}
</div>
@endsection