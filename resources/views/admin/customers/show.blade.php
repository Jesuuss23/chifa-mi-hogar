@extends('layouts.admin')
@section('title', 'Ficha de cliente')
@section('page-title', 'Clientes registrados')
@section('content')
@php($profile = $customer->customerProfile)
<div class="catalog-surface admin-catalog-surface mx-auto max-w-4xl space-y-6">
    <a href="{{ route('admin.customers.index') }}" class="inline-block py-2 text-primary underline">← Volver a clientes</a>
    <section class="border border-border bg-card p-5 sm:p-6" aria-labelledby="customer-title">
        <h1 id="customer-title" class="break-words text-2xl font-bold">{{ $customer->name }}</h1>
        <p class="mt-2 text-sm text-foreground/75">Cliente #{{ $customer->id }} · Registrado el {{ $customer->created_at?->format('d/m/Y') }}</p>
        <dl class="mt-6 grid gap-5 sm:grid-cols-2">
            <div><dt class="text-sm font-semibold">Correo electrónico</dt><dd class="mt-1 break-all">{{ $customer->email }}</dd></div>
            <div><dt class="text-sm font-semibold">Teléfono</dt><dd class="mt-1">{{ $profile?->phone ?: 'Sin registrar' }}</dd></div>
        </dl>
    </section>
    <section class="border border-border bg-card p-5 sm:p-6" aria-labelledby="customer-delivery-title">
        <h2 id="customer-delivery-title" class="text-xl font-bold">Dirección guardada en su cuenta</h2>
        <p class="mt-2 text-sm text-foreground/75">El cliente puede usar otra dirección para cada compra. Para una entrega, consulta siempre los datos del pedido.</p>
        @if(!$profile)
            <p class="mt-4">Este cliente todavía no ha completado sus datos de entrega.</p>
        @endif
        <dl class="mt-5 space-y-5">
            <div><dt class="text-sm font-semibold">Dirección</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $profile?->delivery_address ?: 'Sin registrar' }}</dd></div>
            <div><dt class="text-sm font-semibold">Referencia</dt><dd class="mt-1 whitespace-pre-line break-words">{{ $profile?->delivery_reference ?: 'Sin registrar' }}</dd></div>
            <div>
                <dt class="text-sm font-semibold">Ubicación guardada</dt>
                <dd class="mt-1 break-words">
                    @if($profile?->delivery_latitude !== null && $profile?->delivery_longitude !== null)
                        Latitud: {{ $profile->delivery_latitude }} · Longitud: {{ $profile->delivery_longitude }}
                    @else
                        Sin registrar
                    @endif
                </dd>
            </div>
        </dl>
    </section>
</div>
@endsection
