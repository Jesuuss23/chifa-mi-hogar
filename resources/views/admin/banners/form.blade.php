@extends('layouts.admin')
@section('title', $banner->exists ? 'Editar banner' : 'Crear banner')
@section('page-title', $banner->exists ? 'Editar banner' : 'Crear banner')
@section('content')
@php
    $targetType = old('target_type', $banner->combo_id ? 'combo' : 'product');
@endphp
<div class="admin-catalog-surface catalog-surface mx-auto max-w-4xl">
    <a href="{{ route('admin.banners.index') }}" class="text-primary">← Volver a banners</a>
    <h1 class="my-5 text-2xl font-bold">{{ $banner->exists ? 'Editar banner' : 'Nueva promoción' }}</h1>
    @if($errors->any())
        <ul role="alert" class="mb-5 list-disc rounded-xl bg-destructive/10 p-5 text-destructive">
            @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
        </ul>
    @endif
    <form method="POST" enctype="multipart/form-data" action="{{ $banner->exists ? route('admin.banners.update', $banner) : route('admin.banners.store') }}" class="space-y-6" data-banner-form>
        @csrf
        @if($banner->exists) @method('PUT') @endif
        <section class="space-y-4 rounded-2xl border bg-card p-5">
            <h2 class="text-xl font-bold">Contenido y enlace</h2>
            <div><label for="banner-title" class="block font-semibold">Nombre de la promoción</label>
                <input id="banner-title" name="title" required maxlength="150" value="{{ old('title', $banner->title) }}" class="mt-2 w-full rounded-xl border p-3" aria-describedby="banner-title-help">
                <p id="banner-title-help" class="mt-2 text-sm text-foreground/75">También describe la imagen para lectores de pantalla.</p>
            </div>
            <div><label for="banner-target" class="block font-semibold">Al hacer clic, abrir</label>
                <select id="banner-target" name="target_type" class="mt-2 w-full rounded-xl border p-3" data-banner-target>
                    <option value="product" @selected($targetType === 'product')>Un plato</option>
                    <option value="combo" @selected($targetType === 'combo')>Un combo</option>
                </select>
            </div>
            <div data-banner-destination="product" @if($targetType !== 'product') hidden @endif><label for="banner-product" class="block font-semibold">Plato</label>
                <select id="banner-product" name="product_id" @disabled($targetType !== 'product') @required($targetType === 'product') class="mt-2 w-full rounded-xl border p-3">
                    <option value="">Seleccionar plato</option>
                    @foreach($products as $product)<option value="{{ $product->id }}" @selected((string) old('product_id', $banner->product_id) === (string) $product->id)>{{ $product->name }}</option>@endforeach
                </select>
            </div>
            <div data-banner-destination="combo" @if($targetType !== 'combo') hidden @endif><label for="banner-combo" class="block font-semibold">Combo</label>
                <select id="banner-combo" name="combo_id" @disabled($targetType !== 'combo') @required($targetType === 'combo') class="mt-2 w-full rounded-xl border p-3">
                    <option value="">Seleccionar combo</option>
                    @foreach($combos as $combo)<option value="{{ $combo->id }}" @selected((string) old('combo_id', $banner->combo_id) === (string) $combo->id)>{{ $combo->name }}</option>@endforeach
                </select>
            </div>
            <div><label for="banner-order" class="block font-semibold">Orden</label>
                <input id="banner-order" type="number" name="sort_order" min="0" max="10000" required value="{{ old('sort_order', $banner->sort_order ?? 0) }}" class="mt-2 w-32 rounded-xl border p-3">
            </div>
            <input type="hidden" name="is_active" value="0">
            <label class="flex items-center gap-3"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', $banner->is_active))> Publicar banner</label>
        </section>
        <section class="space-y-4 rounded-2xl border bg-card p-5">
            <h2 class="text-xl font-bold">Imagen principal</h2>
            <p class="text-sm text-foreground/75">Recomendado: 1600 × 500 px. Horizontal, entre 2:1 y 4:1; mínimo 800 × 200 px, máximo 3200 × 1600 px. JPG, PNG o WebP; hasta 5 MB. Se muestra completa.</p>
            @if($banner->mediaAsset)<x-catalog-image :item="$banner" variant="detail" class="max-h-64 w-full rounded-xl object-contain" />@endif
            <label for="banner-image" class="block font-semibold">Subir imagen</label>
            <input id="banner-image" name="image" type="file" accept="image/jpeg,image/png,image/webp" @required(!$banner->exists) class="w-full rounded-xl border p-3">
            <x-admin-image-preview input="banner-image" :max-mb="5" />
        </section>
        <section class="space-y-4 rounded-2xl border bg-card p-5">
            <h2 class="text-xl font-bold">Imagen para celular <span class="text-sm font-normal">(opcional)</span></h2>
            <p class="text-sm text-foreground/75">Recomendado: 900 × 600 px. Horizontal, entre 1.25:1 y 2:1; mínimo 600 × 300 px, máximo 1800 × 1440 px. Hasta 5 MB. Sin esta versión se usa la principal.</p>
            @if($banner->mobileMediaAsset)
                <img src="{{ $banner->mobileMediaAsset->detailUrl() }}" alt="Banner actual para celular" class="max-h-64 rounded-xl object-contain">
                <label class="flex items-center gap-3"><input type="checkbox" name="remove_mobile_image" value="1" @checked(old('remove_mobile_image'))> Quitar versión para celular</label>
            @endif
            <label for="banner-mobile-image" class="block font-semibold">Subir imagen para celular</label>
            <input id="banner-mobile-image" name="mobile_image" type="file" accept="image/jpeg,image/png,image/webp" class="w-full rounded-xl border p-3">
            <x-admin-image-preview input="banner-mobile-image" :max-mb="5" />
        </section>
        <button type="submit" class="rounded-full bg-primary px-6 py-3 font-bold text-primary-foreground">Guardar banner</button>
    </form>
</div>
@endsection
