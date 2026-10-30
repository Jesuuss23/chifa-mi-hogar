@extends('layouts.admin')

@section('page-title', 'Categorías')

@section('content')
@php $editing = $category->exists; @endphp

<div class="catalog-surface admin-catalog-surface mx-auto max-w-3xl">
    <h1 class="mb-6 text-2xl font-bold">
        {{ $editing ? 'Editar categoría' : 'Nueva categoría' }}
    </h1>

    @if($editing)
        <p class="mb-4 text-sm text-foreground/75">
            Puedes cambiar el nombre sin modificar el enlace público existente.
        </p>
    @endif

    <form method="POST"
          enctype="multipart/form-data"
          action="{{ $editing ? route('admin.categories.update', $category) : route('admin.categories.store') }}"
          class="space-y-5 rounded-none border bg-card p-6 shadow-storefront">
        @csrf
        @if($editing) @method('PUT') @endif

        <div>
            <label for="admin-categories-name" class="mb-2 block font-medium">Nombre *</label>
            <input id="admin-categories-name" name="name" type="text" required maxlength="100"
                   value="{{ old('name', $category->name) }}"
                   class="w-full rounded-none border p-3">
            @error('name') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="admin-categories-description" class="mb-2 block font-medium">Descripción</label>
            <textarea id="admin-categories-description" name="description" rows="3"
                      class="w-full rounded-none border p-3">{{ old('description', $category->description) }}</textarea>
        </div>

        <div>
            <label for="admin-categories-image" class="mb-2 block font-medium">Fotografía *</label>
            @if($editing && ($category->mediaAsset || $category->image_path))
                <x-catalog-image :item="$category" variant="card"
                    class="mb-3 aspect-[16/9] w-64 rounded-none object-cover" />
            @endif

            <input id="admin-categories-image" name="image" type="file"
                   accept="image/jpeg,image/png,image/webp"
                   @required(!$editing || (!$category->media_asset_id && !$category->image_path))
                   class="w-full rounded-none border p-3">
            <p class="mt-1 text-xs text-foreground/75">JPG, PNG o WebP. Máximo 5 MB.</p>
            @error('image') <p class="text-sm text-destructive">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="admin-categories-sort_order" class="mb-2 block font-medium">Orden</label>
            <input id="admin-categories-sort_order" name="sort_order" type="number" min="0" required
                   value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                   class="w-full rounded-none border p-3">
        </div>

        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-3">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $category->is_active ?? true))>
            Categoría activa
        </label>

        <div class="flex flex-wrap gap-3 border-t pt-5">
            <button class="rounded-none bg-primary px-6 py-3 font-semibold text-primary-foreground">
                Guardar
            </button>
            <a href="{{ route('admin.categories.index') }}"
               class="rounded-none border px-6 py-3">Cancelar</a>
        </div>
    </form>
</div>
@endsection