@extends('layouts.admin')

@section('content')
@php $editing = $category->exists; @endphp

<div class="mx-auto max-w-3xl px-4 py-6">
    <h1 class="mb-6 text-2xl font-bold">
        {{ $editing ? 'Editar categoría de adicional' : 'Nueva categoría de adicional' }}
    </h1>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.extra-categories.update', $category) : route('admin.extra-categories.store') }}"
          class="space-y-5 rounded-2xl border bg-white p-6">
        @csrf
        @if($editing) @method('PUT') @endif

        @if($errors->any())
            <div class="rounded-xl bg-red-50 p-4 text-red-700">
                {{ $errors->first() }}
            </div>
        @endif

        <div>
            <label class="mb-2 block font-medium">Nombre *</label>
            <input name="name" required maxlength="120"
                   value="{{ old('name', $category->name) }}"
                   placeholder="Ejemplo: Bebidas"
                   class="w-full rounded-xl border p-3">
        </div>

        <div>
            <label class="mb-2 block font-medium">Descripción</label>
            <textarea name="description" rows="3"
                      class="w-full rounded-xl border p-3">{{ old('description', $category->description) }}</textarea>
        </div>

        <div>
            <label class="mb-2 block font-medium">Imagen de categoría</label>
            @if($editing && ($category->mediaAsset || $category->image_path))
                <x-catalog-image :item="$category" variant="card"
                    class="mb-3 aspect-[16/9] w-64 rounded-xl object-cover" />
            @endif
            <input type="file" name="image"
                   accept="image/jpeg,image/png,image/webp"
                   class="w-full rounded-xl border p-3">
            <p class="mt-1 text-xs text-slate-500">Máximo 5 MB.</p>
        </div>

        <div>
            <label class="mb-2 block font-medium">Orden</label>
            <input name="sort_order" type="number" min="0" required
                   value="{{ old('sort_order', $category->sort_order ?? 0) }}"
                   class="w-full rounded-xl border p-3">
        </div>

        <input type="hidden" name="is_active" value="0">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_active" value="1"
                   @checked(old('is_active', $category->is_active ?? true))>
            Categoría activa
        </label>

        <div class="flex gap-3 border-t pt-5">
            <button class="rounded-xl bg-orange-600 px-6 py-3 font-semibold text-white">
                Guardar categoría
            </button>
            <a href="{{ route('admin.extra-categories.index') }}"
               class="rounded-xl border px-6 py-3">Cancelar</a>
        </div>
    </form>
</div>
@endsection