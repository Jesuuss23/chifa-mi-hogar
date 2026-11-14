@extends('layouts.admin')
@section('title', 'Banners')
@section('page-title', 'Banners promocionales')
@section('content')
<div class="admin-catalog-surface catalog-surface mx-auto max-w-6xl">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div><h1 class="text-2xl font-bold">Banners</h1><p class="mt-2 text-sm text-foreground/75">Promociones que llevan directo a un plato o combo.</p></div>
        <a href="{{ route('admin.banners.create') }}" class="rounded-full bg-primary px-5 py-3 font-bold text-primary-foreground">+ Crear banner</a>
    </div>
    <p class="my-5 text-sm text-foreground/75">Solo aparecen si están activos, tienen imagen y su plato o combo está disponible. El orden menor aparece primero.</p>
    <div class="grid gap-5 md:grid-cols-2">
        @forelse($banners as $banner)
            <article class="overflow-hidden rounded-2xl border border-border bg-card">
                <x-catalog-image :item="$banner" class="aspect-[8/3] w-full object-contain bg-muted" />
                <div class="space-y-3 p-5">
                    <h2 class="text-lg font-bold">{{ $banner->title }}</h2>
                    <p class="text-sm">{{ $banner->product?->name ?? $banner->combo?->name ?? 'Destino eliminado: el banner no se publica' }}</p>
                    <p class="text-sm">Orden {{ $banner->sort_order }} · {{ $banner->is_active ? 'Activo' : 'Oculto' }}</p>
                    <div class="flex flex-wrap gap-3">
                        <a href="{{ route('admin.banners.edit', $banner) }}" class="rounded-full border px-4 py-2">Editar</a>
                        <form action="{{ route('admin.banners.destroy', $banner) }}" method="POST" onsubmit="return confirm('¿Eliminar este banner?')">
                            @csrf @method('DELETE')
                            <button class="rounded-full border border-destructive px-4 py-2 text-destructive" type="submit">Eliminar</button>
                        </form>
                    </div>
                </div>
            </article>
        @empty
            <p class="rounded-2xl border border-dashed p-8">Crea tu primer banner. Mientras no haya promociones, se conserva la portada actual.</p>
        @endforelse
    </div>
    <div class="mt-6">{{ $banners->links() }}</div>
</div>
@endsection
