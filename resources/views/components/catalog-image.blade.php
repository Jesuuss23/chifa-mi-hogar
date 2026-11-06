@props([
    'item',
    'variant' => 'card',
    'class' => 'w-full h-full object-cover',
    'loading' => 'lazy',
])

@php
    $src = app(\App\Services\CatalogImageService::class)->url($item, $variant);

    // Texto alternativo para accesibilidad
    $alt = $item->name ?? $item->title ?? 'Imagen del catálogo';
@endphp

@if($src)
    <img
        src="{{ $src }}"
        alt="{{ $alt }}"
        loading="{{ $loading }}"
        decoding="async"
        class="{{ $class }}"
    >
@else
    <div class="flex items-center justify-center bg-slate-100 text-slate-400 {{ $class }}">
        <span class="text-sm">Sin imagen</span>
    </div>
@endif