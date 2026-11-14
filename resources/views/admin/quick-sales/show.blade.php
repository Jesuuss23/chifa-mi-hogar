@extends('layouts.admin')
@section('page-title', 'Venta rápida #'.$sale->id)
@section('content')
<div class="mx-auto max-w-3xl space-y-5">
    @if(session('success'))<p role="status" class="rounded-xl border bg-card p-4">{{ session('success') }}</p>@endif
    <div class="flex flex-wrap gap-3">
        <a href="{{ route('admin.quick-sales.create') }}" class="rounded-full bg-primary px-5 py-3 font-bold text-primary-foreground">+ Nueva venta</a>
        <a href="{{ route('admin.quick-sales.index', ['date' => $sale->sold_on->format('Y-m-d')]) }}" class="rounded-full border px-5 py-3">Ver ventas del día</a>
    </div>
    <section class="rounded-2xl border bg-card p-5">
        <h1 class="text-2xl font-bold">Venta #{{ $sale->id }}</h1>
        <p class="mt-2">{{ $sale->sold_on->format('d/m/Y') }} · {{ $sale->payment_method === 'cash' ? 'Efectivo' : 'Yape' }}</p>
        <p class="mt-2">{{ $sale->customer_name ?: 'Sin nombre' }}</p>
        <ul class="my-5 divide-y">
            @foreach($sale->items as $item)
                <li class="flex justify-between gap-3 py-3"><span>{{ $item['quantity'] }} × {{ $item['name'] }}</span><strong class="whitespace-nowrap">S/ {{ number_format($item['line_total_cents'] / 100, 2) }}</strong></li>
            @endforeach
        </ul>
        @if($sale->total_cents !== $sale->catalog_total_cents)<p class="text-sm">Total según carta: S/ {{ number_format($sale->catalog_total_cents / 100, 2) }}</p>@endif
        <p class="mt-2 text-2xl font-bold">Total cobrado: S/ {{ number_format($sale->total_cents / 100, 2) }}</p>
        <p class="mt-4 text-sm text-muted-foreground">Registrado por {{ $sale->creator?->name }} · {{ $sale->created_at->timezone('America/Lima')->format('d/m/Y H:i') }}</p>
    </section>
</div>
@endsection
