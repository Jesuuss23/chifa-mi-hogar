@extends('layouts.admin')

@section('title', 'Reseñas de Google')
@section('page-title', 'Reseñas de Google')

@section('content')
<div class="catalog-surface admin-catalog-surface max-w-6xl mx-auto space-y-6">

    <div class="flex flex-col sm:flex-row sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-foreground">
                Reseñas de Google
            </h1>
            <p class="text-foreground/75 text-sm mt-1">
                Calificaciones públicas de ChifaMiHogar.
            </p>
        </div>

    </div>

    @if($error)
        <div role="alert" class="rounded-none bg-destructive/10 text-destructive p-5">
            {{ $error }}
        </div>
    @elseif($business)
        <div class="bg-card rounded-none border shadow-storefront p-6">
            <h2 class="text-xl font-bold">
                {{ $business['name'] }}
            </h2>

            <div class="flex flex-wrap items-center gap-3 mt-4">
                <span class="text-5xl font-bold">
                    {{ $business['rating'] ?? '—' }}
                </span>

                <div>
                    <div class="text-amber-500 text-xl">
                        @for($i = 1; $i <= 5; $i++)
                            {{ $i <= floor($business['rating'] ?? 0) ? '★' : '☆' }}
                        @endfor
                    </div>

                    <p class="text-foreground/75 text-sm">
                        {{ $business['total_ratings'] }}
                        valoraciones en Google
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 mt-6">
                <a href="{{ $business['write_review_url'] }}"
                   target="_blank"
                   rel="noopener noreferrer"
                   class="rounded-none bg-primary text-primary-foreground px-5 py-3 font-semibold">
                    Escribir reseña en Google
                </a>

                @if($business['google_maps_url'])
                    <a href="{{ $business['google_maps_url'] }}"
                       target="_blank"
                       rel="noopener noreferrer"
                       class="rounded-none border px-5 py-3 font-semibold">
                        Abrir Google Maps
                    </a>
                @endif
            </div>
        </div>

        <div class="space-y-4">
            <h2 class="text-xl font-bold">
                Comentarios disponibles
            </h2>

            @forelse($business['reviews'] as $review)
                <div class="bg-card rounded-none border p-5 space-y-3">
                    <div class="flex flex-wrap justify-between gap-3">
                        <div>
                            <p class="font-semibold">
                                {{ $review['author'] ?? 'Usuario de Google' }}
                            </p>

                            <p class="text-amber-500">
                                @for($i = 1; $i <= 5; $i++)
                                    {{ $i <= ($review['rating'] ?? 0) ? '★' : '☆' }}
                                @endfor
                            </p>
                        </div>

                        <span class="text-xs text-foreground/75">
                            {{ $review['relative_time'] ?? '' }}
                        </span>
                    </div>

                    <p class="text-foreground/75 whitespace-pre-line">
                        {{ $review['comment'] ?? 'Sin comentario de texto.' }}
                    </p>

                    @if($review['author_url'])
                        <a href="{{ $review['author_url'] }}"
                           target="_blank"
                           rel="noopener noreferrer"
                           class="text-primary text-sm">
                            Ver perfil en Google
                        </a>
                    @endif
                </div>
            @empty
                <div class="bg-card rounded-none border p-8 text-center">
                    <p class="font-semibold text-foreground/75">
                        No hay comentarios disponibles mediante la API.
                    </p>
                    <p class="text-foreground/75 text-sm mt-2">
                        Google registra las valoraciones, aunque no siempre
                        devuelve comentarios escritos.
                    </p>
                </div>
            @endforelse
        </div>

        <p class="text-sm text-foreground/75">
            Información proporcionada por Google Maps.
            Las reseñas pertenecen a sus respectivos autores.
        </p>
    @endif
</div>
@endsection