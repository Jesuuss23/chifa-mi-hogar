@extends('layouts.admin')
@section('title', 'Apariencia')
@section('page-title', 'Apariencia de la tienda')
@section('content')
<div class="mx-auto max-w-5xl" data-appearance>
    <p class="text-sm">Tema actual: <strong>{{ $themes[$activeTheme] }}</strong>.</p>
    <p class="mt-2 text-sm">La vista previa es privada. Tus clientes verán el cambio solo cuando pulses Aplicar tema.</p>
    <form method="POST" action="{{ route('admin.appearance.update') }}" class="mt-6">
        @csrf
        @method('PUT')
        <fieldset>
            <legend class="text-lg font-semibold">Temas disponibles</legend>
            @error('theme')<p class="mt-2 text-destructive" role="alert">{{ $message }}</p>@enderror
            <div class="mt-4 grid gap-5 md:grid-cols-2">
                @foreach($themes as $key => $label)
                <div class="appearance-card rounded-xl border border-border bg-card p-5">
                    <div class="appearance-swatch appearance-swatch--{{ $key }}" aria-hidden="true">
                        <span>CHIFA MI HOGAR</span>
                        <div class="appearance-swatch-card"><strong>Hecho para compartir</strong><span class="appearance-swatch-button">Ver carta</span></div>
                    </div>
                    <label class="mt-5 flex cursor-pointer items-center gap-3 font-semibold">
                        <input type="radio" name="theme" value="{{ $key }}" @checked(old('theme', $activeTheme) === $key) required>
                        {{ $label }}
                        @if($activeTheme === $key)<span class="ml-auto text-xs font-normal">Activo</span>@endif
                    </label>
                    <p class="mt-3 text-sm text-foreground/75">{{ match ($key) {
                        'formal' => 'Borgoña, fondos claros, Manrope y botones redondeados.',
                        'christmas' => 'Verde pino, rojo y dorado. Bastones, gorrito y nieve en la portada, con opción de pausa.',
                        default => 'Tonos cálidos, tipografía monoespaciada y acabados rectos.',
                    } }}</p>
                    <a href="{{ route('admin.appearance.preview', ['theme' => $key]) }}" data-theme-preview data-theme-label="{{ $label }}" target="_blank" rel="noopener"
                       class="mt-4 inline-block rounded-lg border border-border px-4 py-2 text-sm font-semibold underline">Previsualizar {{ $label }}</a>
                </div>
                @endforeach
            </div>
        </fieldset>
        <div class="mt-6 flex flex-wrap items-center gap-4">
            <button type="submit" class="rounded-xl bg-primary px-6 py-3 font-semibold text-primary-foreground">Aplicar tema</button>
            <p class="text-sm">Puedes volver al otro tema cuando quieras.</p>
        </div>
    </form>
    <p class="mt-6 text-sm text-foreground/75">Se mantienen la distribución de la carta, los productos y los flujos de compra. El administrador conserva su apariencia.</p>
    <dialog class="appearance-dialog" aria-labelledby="appearance-preview-title">
        <div class="appearance-dialog-toolbar">
            <div>
                <h2 id="appearance-preview-title" class="font-semibold">Vista previa: <span data-preview-label></span></h2>
                <p class="mt-1 text-sm">Solo visualización. Los enlaces y compras están desactivados.</p>
            </div>
            <button type="button" data-preview-close class="rounded-lg border border-border px-4 py-2" autofocus>Cerrar</button>
        </div>
        <div class="appearance-dialog-toolbar">
            <div role="group" aria-label="Ancho de la vista previa" class="flex gap-2">
                <button type="button" data-preview-width="desktop" aria-pressed="true" class="rounded-lg border border-border px-3 py-2">Escritorio</button>
                <button type="button" data-preview-width="mobile" aria-pressed="false" class="rounded-lg border border-border px-3 py-2">Móvil</button>
            </div>
            <p class="text-xs">Cierra la vista previa para elegir y aplicar el tema.</p>
        </div>
        <div class="appearance-preview-stage">
            <iframe data-preview-frame title="Vista previa visual de la carta" sandbox="allow-same-origin" referrerpolicy="no-referrer"></iframe>
        </div>
    </dialog>
</div>
@endsection
