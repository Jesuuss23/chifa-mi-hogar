@extends('layouts.admin')

@section('page-title', 'Adicionales')

@section('content')
@php $editing = $extra->exists; @endphp

<div class="catalog-surface admin-catalog-surface mx-auto max-w-3xl">
    <h1 class="mb-6 text-2xl font-bold">
        {{ $editing ? 'Editar adicional' : 'Nuevo adicional' }}
    </h1>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.extras.update', $extra) : route('admin.extras.store') }}"
          class="space-y-5 rounded-none border bg-card p-6">
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

        <div>
            <label for="admin-extras-name" class="mb-2 block font-medium">Nombre *</label>
            <input id="admin-extras-name" name="name" required maxlength="120"
                   value="{{ old('name', $extra->name) }}"
                   placeholder="Ejemplo: Wantán frito"
                   class="w-full rounded-none border p-3">
        </div>

        <div>
            <label for="admin-extras-extra_category_id" class="mb-2 block font-medium">Categoría *</label>
            <select id="admin-extras-extra_category_id" name="extra_category_id" required
                    class="w-full rounded-none border p-3">
                <option value="">Seleccionar categoría</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}"
                        @selected(old('extra_category_id', $extra->extra_category_id) == $category->id)>
                        {{ $category->name }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label for="admin-extras-description" class="mb-2 block font-medium">Descripción</label>
            <textarea id="admin-extras-description" name="description" rows="3"
                      class="w-full rounded-none border p-3">{{ old('description', $extra->description) }}</textarea>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label for="admin-extras-price" class="mb-2 block font-medium">Precio (S/) *</label>
                <input id="admin-extras-price" name="price" type="number" step="0.01" min="0" required
                       value="{{ old('price', $extra->price) }}"
                       class="w-full rounded-none border p-3">
            </div>
            <div>
                <label for="admin-extras-sort_order" class="mb-2 block font-medium">Orden</label>
                <input id="admin-extras-sort_order" name="sort_order" type="number" min="0" required
                       value="{{ old('sort_order', $extra->sort_order ?? 0) }}"
                       class="w-full rounded-none border p-3">
            </div>
        </div>

        <div>
            <label for="admin-extras-image" class="mb-2 block font-medium">Fotografía</label>
            @if($editing && ($extra->mediaAsset || $extra->image_path))
                <x-catalog-image :item="$extra" variant="card"
                    class="mb-3 aspect-[4/3] w-64 rounded-none object-cover" />
            @endif
            <input id="admin-extras-image" name="image" type="file"
                   accept="image/jpeg,image/png,image/webp"
                   class="w-full rounded-none border p-3">
            <p class="mt-1 text-xs text-foreground/75">JPG, PNG o WebP. Máximo 5 MB.</p>
        </div>

        <input type="hidden" name="is_available" value="0">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_available" value="1"
                   @checked(old('is_available', $extra->is_available ?? true))>
            Disponible para pedidos
        </label>

        <p class="rounded-none bg-accent p-4 text-sm text-foreground">
            Los adicionales solo podrán comprarse junto con un plato compatible.
        </p>

        <div class="flex flex-wrap gap-3 border-t pt-5">
            <button class="rounded-none bg-primary px-6 py-3 font-semibold text-primary-foreground">
                Guardar adicional
            </button>
            <a href="{{ route('admin.extras.index') }}"
               class="rounded-none border px-6 py-3">Cancelar</a>
        </div>
    </form>
</div>
@endsection