@extends('layouts.admin')

@section('content')
@php $editing = $extra->exists; @endphp

<div class="mx-auto max-w-3xl px-4 py-6">
    <h1 class="mb-6 text-2xl font-bold">
        {{ $editing ? 'Editar adicional' : 'Nuevo adicional' }}
    </h1>

    <form method="POST" enctype="multipart/form-data"
          action="{{ $editing ? route('admin.extras.update', $extra) : route('admin.extras.store') }}"
          class="space-y-5 rounded-2xl border bg-white p-6">
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
            <label class="mb-2 block font-medium">Nombre *</label>
            <input name="name" required maxlength="120"
                   value="{{ old('name', $extra->name) }}"
                   placeholder="Ejemplo: Wantán frito"
                   class="w-full rounded-xl border p-3">
        </div>

        <div>
            <label class="mb-2 block font-medium">Categoría *</label>
            <select name="extra_category_id" required
                    class="w-full rounded-xl border p-3">
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
            <label class="mb-2 block font-medium">Descripción</label>
            <textarea name="description" rows="3"
                      class="w-full rounded-xl border p-3">{{ old('description', $extra->description) }}</textarea>
        </div>

        <div class="grid gap-5 sm:grid-cols-2">
            <div>
                <label class="mb-2 block font-medium">Precio (S/) *</label>
                <input name="price" type="number" step="0.01" min="0" required
                       value="{{ old('price', $extra->price) }}"
                       class="w-full rounded-xl border p-3">
            </div>
            <div>
                <label class="mb-2 block font-medium">Orden</label>
                <input name="sort_order" type="number" min="0" required
                       value="{{ old('sort_order', $extra->sort_order ?? 0) }}"
                       class="w-full rounded-xl border p-3">
            </div>
        </div>

        <div>
            <label class="mb-2 block font-medium">Fotografía</label>
            @if($editing && ($extra->mediaAsset || $extra->image_path))
                <x-catalog-image :item="$extra" variant="card"
                    class="mb-3 aspect-[4/3] w-64 rounded-xl object-cover" />
            @endif
            <input name="image" type="file"
                   accept="image/jpeg,image/png,image/webp"
                   class="w-full rounded-xl border p-3">
            <p class="mt-1 text-xs text-slate-500">JPG, PNG o WebP. Máximo 5 MB.</p>
        </div>

        <input type="hidden" name="is_available" value="0">
        <label class="flex items-center gap-2">
            <input type="checkbox" name="is_available" value="1"
                   @checked(old('is_available', $extra->is_available ?? true))>
            Disponible para pedidos
        </label>

        <p class="rounded-xl bg-orange-50 p-4 text-sm text-orange-800">
            Los adicionales solo podrán comprarse junto con un plato compatible.
        </p>

        <div class="flex gap-3 border-t pt-5">
            <button class="rounded-xl bg-orange-600 px-6 py-3 font-semibold text-white">
                Guardar adicional
            </button>
            <a href="{{ route('admin.extras.index') }}"
               class="rounded-xl border px-6 py-3">Cancelar</a>
        </div>
    </form>
</div>
@endsection