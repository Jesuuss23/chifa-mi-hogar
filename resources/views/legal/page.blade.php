@extends('layouts.storefront')

@section('title', $title . ' | Chifa Mi Hogar')
@section('meta-description', $title . ' de Chifa Mi Hogar: información del negocio y uso de tus datos.')

@if($preview)
    @push('head')
        <meta name="robots" content="noindex, nofollow">
    @endpush
@endif

@section('content')
<article class="customer-surface leading-7 mx-auto max-w-3xl space-y-6 px-4 py-10 text-foreground">
    @if($preview)
        <aside class="rounded-none border border-amber-300 bg-amber-50 p-4">
            Vista previa para administración. Los campos pendientes deben completarse antes de publicar.
            <a class="font-semibold underline" href="{{ route('admin.settings.edit') }}">Volver a Configuración</a>
        </aside>
    @endif
    <h1 class="text-3xl font-bold">{{ $title }}</h1>
    <nav class="flex flex-wrap gap-4 text-sm" aria-label="Documentos informativos">
        @foreach($pages as $key => $label)
            <a class="underline" href="{{ $preview ? route('admin.legal.preview', $key) : route('legal.' . $key) }}">{{ $label }}</a>
        @endforeach
    </nav>
    @include('legal.' . $page)
</article>
@endsection
