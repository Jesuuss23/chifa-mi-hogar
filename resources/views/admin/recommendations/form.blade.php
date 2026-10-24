@extends('layouts.admin')

@section('content')
@php
    $editing = $recommendation->exists;
    $chosen = old('extras', $selectedExtras);
@endphp

<div class="mx-auto max-w-4xl px-4 py-6">
    <a href="{{ route('admin.products.recommendations.index', $product) }}"
       class="text-sm text-orange-600">← Volver a recomendaciones</a>

    <h1 class="mb-2 mt-3 text-2xl font-bold">
        {{ $editing ? 'Editar recomendación' : 'Nueva recomendación' }}
    </h1>
    <p class="mb-6 text-sm text-slate-500">
        Plato base: {{ $product->name }} — S/ {{ number_format($product->price, 2) }}
    </p>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing
              ? route('admin.products.recommendations.update', [$product, $recommendation])
              : route('admin.products.recommendations.store', $product) }}"
          class="space-y-6 rounded-2xl border bg-white p-6">
        @csrf
        @if($editing) @method('PUT') @endif

        @if($errors->any())
            <div class="rounded-xl bg-red-50 p-4 text-red-700">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div>
            <label class="mb-2 block font-medium">Nombre de la recomendación *</label>
            <input name="name" required maxlength="150"
                   value="{{ old('name', $recommendation->name) }}"
                   placeholder="Ejemplo: Chaufa con Wantán"
                   class="w-full rounded-xl border p-3">
        </div>

        <div>
            <label class="mb-2 block font-medium">Descripción</label>
            <textarea name="description" rows="3"
                      class="w-full rounded-xl border p-3">{{ old('description', $recommendation->description) }}</textarea>
        </div>

        <div>
            <label class="mb-2 block font-medium">Fotografía de la combinación *</label>
            @if($editing && ($recommendation->mediaAsset || $recommendation->image_path))
                <x-catalog-image :item="$recommendation" variant="card"
                    class="mb-3 aspect-[4/3] w-64 rounded-xl object-cover" />
            @endif
            <input name="image" type="file"
                   accept="image/jpeg,image/png,image/webp"
                   @required(!$editing || (!$recommendation->media_asset_id && !$recommendation->image_path))
                   class="w-full rounded-xl border p-3">
            <p class="mt-1 text-xs text-slate-500">
                Fotografía de la combinación completa. Máximo 5 MB.
            </p>
        </div>

        <div class="rounded-xl border p-5">
            <h2 class="mb-2 font-bold">Adicionales de esta recomendación</h2>
            <p class="mb-4 text-sm text-slate-500">
                Selecciona los adicionales incluidos en la combinación.
                Solo aparecen los permitidos para este plato.
            </p>

            <div class="grid gap-3 sm:grid-cols-2">
                @forelse($extras as $extra)
                    <label class="flex items-center gap-3 rounded-xl border p-3">
                        <input type="checkbox" name="extras[]"
                               value="{{ $extra->id }}"
                               @checked(in_array($extra->id, $chosen))>

                        <x-catalog-image :item="$extra" variant="thumbnail"
                            class="h-14 w-14 rounded-lg object-cover" />

                        <span class="flex-1 text-sm">
                            <span class="block font-semibold">{{ $extra->name }}</span>
                            <span class="text-xs text-slate-500">
                                {{ $extra->extraCategory?->name ?? 'Adicional' }}
                            </span>
                            <span class="block font-medium text-orange-600">
                                + S/ {{ number_format($extra->price, 2) }}
                            </span>
                        </span>
                    </label>
                @empty
                    <p class="text-sm text-slate-500">
                        Este plato no tiene adicionales permitidos.
                        Edítalo primero para asignarlos.
                    </p>
                @endforelse
            </div>
        </div>

        <div>
            <label class="mb-2 block font-medium">Orden</label>
            <input name="sort_order" type="number" min="0" required
                   value="{{ old('sort_order', $recommendation->sort_order ?? 0) }}"
                   class="w-full rounded-xl border p-3">
        </div>

        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $recommendation->is_active ?? true))>
            Recomendación activa
        </label>

        <p class="rounded-xl bg-orange-50 p-4 text-sm text-orange-800">
            El precio de la recomendación se calcula automáticamente sumando
            el precio del plato y sus adicionales.
        </p>

        <div class="flex gap-3 border-t pt-5">
            <button class="rounded-xl bg-orange-600 px-6 py-3 font-semibold text-white">
                Guardar recomendación
            </button>
            <a href="{{ route('admin.products.recommendations.index', $product) }}"
               class="rounded-xl border px-6 py-3">Cancelar</a>
        </div>
    </form>
</div>
@endsection