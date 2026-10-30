@extends('layouts.admin')

@section('page-title', 'Platos')

@section('content')
@php $editing = $product->exists; @endphp

<div class="catalog-surface admin-catalog-surface mx-auto max-w-4xl">
    <h1 class="mb-6 text-2xl font-bold">
        {{ $editing ? 'Editar plato' : 'Nuevo plato' }}
    </h1>

    @if($editing)
        <p class="mb-4 text-sm text-foreground/75">
            Puedes cambiar el nombre sin modificar el enlace público existente.
        </p>
    @endif

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.products.update', $product) : route('admin.products.store') }}"
          class="space-y-6 rounded-none border bg-card p-6 shadow-storefront">
        @csrf
        @if($editing) @method('PUT') @endif

        @if($errors->any())
            <div class="rounded-none bg-destructive/10 p-4 text-destructive">
                <ul class="list-inside list-disc">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="grid gap-5 md:grid-cols-2">
            <div>
                <label for="admin-products-name" class="mb-2 block font-medium">Nombre *</label>
                <input id="admin-products-name" name="name" required maxlength="150"
                       value="{{ old('name', $product->name) }}"
                       class="w-full rounded-none border p-3">
            </div>

            <div>
                <label for="admin-products-category_id" class="mb-2 block font-medium">Categoría *</label>
                <select id="admin-products-category_id" name="category_id" required class="w-full rounded-none border p-3">
                    <option value="">Seleccionar categoría</option>
                    @foreach($categories as $category)
                        <option value="{{ $category->id }}"
                            @selected(old('category_id', $product->category_id) == $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="admin-products-price" class="mb-2 block font-medium">Precio (S/) *</label>
                <input id="admin-products-price" name="price" type="number" step="0.01" min="0" required
                       value="{{ old('price', $product->price) }}"
                       class="w-full rounded-none border p-3">
            </div>

            <div>
                <label for="admin-products-sort_order" class="mb-2 block font-medium">Orden</label>
                <input id="admin-products-sort_order" name="sort_order" type="number" min="0" required
                       value="{{ old('sort_order', $product->sort_order ?? 0) }}"
                       class="w-full rounded-none border p-3">
            </div>
        </div>

        <div>
            <label for="admin-products-description" class="mb-2 block font-medium">Descripción</label>
            <textarea id="admin-products-description" name="description" rows="4"
                      class="w-full rounded-none border p-3">{{ old('description', $product->description) }}</textarea>
        </div>

        <div>
            <label for="admin-products-image" class="mb-2 block font-medium">Fotografía del plato *</label>
            @if($editing && ($product->mediaAsset || $product->image_path))
                <x-catalog-image :item="$product" variant="card"
                    class="mb-3 aspect-[4/3] w-64 rounded-none object-cover" />
            @endif
            <input id="admin-products-image" name="image" type="file"
                   accept="image/jpeg,image/png,image/webp"
                   @required(!$editing || (!$product->media_asset_id && !$product->image_path))
                   class="w-full rounded-none border p-3">
            <p class="mt-1 text-xs text-foreground/75">Máximo 5 MB. Se optimiza a WebP.</p>
        </div>

        <div class="rounded-none border p-5">
            <h2 class="mb-2 font-bold">Adicionales permitidos</h2>
            <p class="mb-4 text-sm text-foreground/75">
                Selecciona qué adicionales podrá elegir el cliente para este plato.
            </p>

            @php
                $chosen = old('extras', $selectedExtras);
            @endphp

            <div class="grid gap-3 sm:grid-cols-2">
                @forelse($extras as $extra)
                    <label class="flex items-center gap-3 rounded-none border p-3">
                        <input type="checkbox" name="extras[]"
                               value="{{ $extra->id }}"
                               @checked(in_array($extra->id, $chosen))>

                        <x-catalog-image :item="$extra" variant="thumbnail"
                            class="h-12 w-12 rounded-none object-cover" />

                        <span class="flex-1 text-sm">
                            <span class="block font-semibold">{{ $extra->name }}</span>
                            <span class="text-foreground/75">
                                S/ {{ number_format($extra->price, 2) }}
                            </span>
                        </span>
                    </label>
                @empty
                    <p class="text-sm text-foreground/75">No hay adicionales disponibles.</p>
                @endforelse
            </div>
        </div>

        <div class="flex flex-wrap gap-6">
            <label class="flex items-center gap-2">
                <input type="hidden" name="is_available" value="0">
                <input type="checkbox" name="is_available" value="1"
                       @checked(old('is_available', $product->is_available ?? true))>
                Disponible
            </label>

            <label class="flex items-center gap-2">
                <input type="hidden" name="is_featured" value="0">
                <input type="checkbox" name="is_featured" value="1"
                       @checked(old('is_featured', $product->is_featured ?? false))>
                Plato destacado
            </label>
        </div>

        <div class="flex flex-wrap gap-3 border-t pt-5">
            <button class="rounded-none bg-primary px-6 py-3 font-semibold text-primary-foreground">
                Guardar plato
            </button>
            <a href="{{ route('admin.products.index') }}"
               class="rounded-none border px-6 py-3">Cancelar</a>
        </div>
    </form>
</div>
@endsection