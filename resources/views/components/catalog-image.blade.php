@props([
    'item',
    'variant' => 'card',
    'class' => 'w-full h-full object-cover',
    'loading' => 'lazy',
])

@php
    // Obtener la imagen optimizada asociada al registro
    $media = $item->mediaAsset;

    // Seleccionar el tamaño de imagen solicitado
    $path = match ($variant) {
        'thumbnail' => $media?->thumbnail_path,
        'detail' => $media?->detail_path,
        default => $media?->card_path,
    };

    // Generar la URL pública de la imagen
    $src = $path
        ? asset('storage/' . $path)
        : (
            $item->image_path
                ? asset('storage/' . $item->image_path)
                : null
        );

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