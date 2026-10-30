@extends('layouts.admin')

@section('page-title', 'Categorías de adicionales')

@section('content')
@php $editing = $category->exists; @endphp

<div class="catalog-surface admin-catalog-surface mx-auto max-w-3xl">
    <h1 class="mb-6 text-2xl font-bold">
        {{ $editing ? 'Editar categoría de adicional' : 'Nueva categoría de adicional' }}
    </h1>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.extra-categories.update', $category) : route('admin.extra-categories.store') }}"
          class="space-y-5 rounded-none border bg-card p-6">
        @csrf
        @if($editing) @method('PUT') @endif

        @if($errors->any())
            <div class="rounded-none bg-destructive/10 p-4 text-destructive">
                {{ $errors->first() }}
            </div>
        @endif

        <div>
            <label for="admin-extra-categories-name" class="mb-2 block font-medium">Nombre *</label>
            <input id="admin-extra-categories-name" name="name" required maxlength="120"
                   value="{{ old('name', $category->name) }}"
                   placeholder="Ejemplo: Bebidas"
                   class="w-full rounded-none border p-3">
        </div>

        <div>
            <label for="admin-extra-categories-description" class="mb-2 block font-medium">Descripción</label>
            <textarea id="admin-extra-categories-description" name="description" rows="3"
                      class="w-full rounded-none border p-3">{{ old('description', $category->description) }}</textarea>
        </div>

        <div>
            <label for="admin-extra-categories-image" class="mb-2 block font-medium">Imagen de categoría</label>
            @if($editing && ($category->mediaAsset || $category->image_path))
                <x-catalog-image :item="$category" variant="card"
                    class="mb-3 aspect-[16/9] w-64 rounded-none object-cover" />
            @endif
            <input type="file" id="admin-extra-categories-image" name="image"
                   accept="image/jpeg,image/png,image/webp"
                   class="w-full rounded-none border p-3">
            <p class="mt-1 text-xs text-foreground/75">Máximo 5 MB.</p>
        </div>

        <div>
            <label for="admin-extra-categories-sort_order" class="mb-2 block font-medium">Orden</label>
            <input id="admin-extra-categories-sort_order" name="sort_order" type="number" min="0" required
                   value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                   class="w-full rounded-none border p-3">
        </div>

        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $category->is_active ?? true))>
            Categoría activa
        </label>

        <div class="flex flex-wrap gap-3 border-t pt-5">
            <button class="rounded-none bg-primary px-6 py-3 font-semibold text-primary-foreground">
                Guardar categoría
            </button>
            <a href="{{ route('admin.extra-categories.index') }}"
               class="rounded-none border px-6 py-3">Cancelar</a>
        </div>
    </form>
</div>
@endsection