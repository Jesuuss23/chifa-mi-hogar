@extends('layouts.admin')

@section('title', 'Redes sociales')
@section('page-title', 'Redes sociales')

@section('content')
<div class="catalog-surface admin-catalog-surface max-w-4xl mx-auto space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Redes sociales</h1>
        <p class="text-foreground/75">
            Configura las redes que aparecerán en tu página web.
        </p>
    </div>

    @if($errors->any())
        <div role="alert" class="rounded-none bg-destructive/10 p-4 text-destructive">
            {{ $errors->first() }}
        </div>
    @endif

    <form method="POST"
          action="{{ route('admin.social-links.update') }}"
          class="bg-card rounded-none border shadow-storefront p-6 space-y-5">
        @csrf
        @method('PUT')

        @foreach($platforms as $key => $label)
            @php
                $link = $links->get($key);
            @endphp

            <div class="space-y-2 border-b pb-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <label for="social-link-{{ $key }}" class="font-semibold">{{ $label }}</label>

                    <label class="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            name="links[{{ $key }}][is_active]"
                            value="1"
                            @checked(old(
                                "links.$key.is_active",
                                $link?->is_active
                            ))
                        >
                        Mostrar
                    </label>
                </div>

                <input
                    type="url"
                    id="social-link-{{ $key }}"
                    name="links[{{ $key }}][url]"
                    value="{{ old("links.$key.url", $link?->url) }}"
                    placeholder="https://..."
                    class="w-full rounded-none border-input"
                >
            </div>
        @endforeach

        <button type="submit"
                class="rounded-none bg-primary px-6 py-3 text-primary-foreground font-semibold">
            Guardar redes sociales
        </button>
    </form>
</div>
@endsection