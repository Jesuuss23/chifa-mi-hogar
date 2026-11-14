@extends('layouts.admin')
@section('page-title', 'Ventas rápidas')
@section('content')
@php
    $cash = (int) ($totals->firstWhere('payment_method', 'cash')?->amount ?? 0);
    $yape = (int) ($totals->firstWhere('payment_method', 'yape')?->amount ?? 0);
@endphp
<div class="mx-auto max-w-6xl space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div><h1 class="text-2xl font-bold">Ventas rápidas</h1><p class="mt-1 text-sm">Registro de ventas realizadas fuera de la web.</p></div>
        <a href="{{ route('admin.quick-sales.create') }}" class="rounded-full bg-primary px-5 py-3 font-bold text-primary-foreground">+ Nueva venta</a>
    </div>
    @if($errors->any())<p role="alert">{{ $errors->first() }}</p>@endif
    <form method="GET" class="rounded-2xl border bg-card p-4">
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <label>Período<select name="period" class="mt-1 w-full rounded-xl border p-3">@foreach(['day' => 'Día', 'month' => 'Mes', 'year' => 'Año', 'custom' => 'Rango de fechas'] as $value => $label)<option value="{{ $value }}" @selected($period === $value)>{{ $label }}</option>@endforeach</select></label>
            <label>Fecha de referencia<input type="date" name="date" value="{{ $date->format('Y-m-d') }}" class="mt-1 w-full rounded-xl border p-3"></label>
            <label>Pago<select name="payment_method" class="mt-1 w-full rounded-xl border p-3"><option value="">Todos</option><option value="cash" @selected(request('payment_method') === 'cash')>Efectivo</option><option value="yape" @selected(request('payment_method') === 'yape')>Yape</option></select></label>
            <label>Cliente<input name="search" maxlength="120" value="{{ request('search') }}" class="mt-1 w-full rounded-xl border p-3" placeholder="Buscar por nombre"></label>
        </div>
        <details class="mt-3" @if($period === 'custom') open @endif>
            <summary class="cursor-pointer text-sm">Rango personalizado y agrupación</summary>
            <div class="mt-3 grid gap-3 sm:grid-cols-3">
                <label>Desde<input type="date" name="from" value="{{ $from->format('Y-m-d') }}" class="mt-1 w-full rounded-xl border p-3"></label>
                <label>Hasta<input type="date" name="to" value="{{ $to->format('Y-m-d') }}" class="mt-1 w-full rounded-xl border p-3"></label>
                <label>Agrupar por<select name="group" class="mt-1 w-full rounded-xl border p-3"><option value="">Automático</option>@foreach(['day' => 'Día', 'month' => 'Mes', 'year' => 'Año'] as $value => $label)<option value="{{ $value }}" @selected(request('group') === $value)>{{ $label }}</option>@endforeach</select></label>
            </div>
        </details>
        <div class="mt-4 flex gap-3"><button class="rounded-full bg-primary px-5 py-2 text-primary-foreground">Filtrar</button><a href="{{ route('admin.quick-sales.index') }}" class="rounded-full border px-5 py-2">Hoy</a></div>
    </form>
    <p class="text-sm">{{ $from->format('d/m/Y') }} al {{ $to->format('d/m/Y') }} · {{ $totals->sum('sale_count') }} ventas coincidentes</p>
    <div class="grid gap-3 sm:grid-cols-3">
        @foreach(['Total del filtro' => $cash + $yape, 'Efectivo' => $cash, 'Yape' => $yape] as $label => $amount)
            <div class="rounded-2xl border bg-card p-5"><p class="text-sm">{{ $label }}</p><p class="mt-1 text-2xl font-bold">S/ {{ number_format($amount / 100, 2) }}</p></div>
        @endforeach
    </div>
    <details class="rounded-2xl border bg-card p-4" open>
        <summary class="cursor-pointer font-bold">Resumen por {{ ['day' => 'día', 'month' => 'mes', 'year' => 'año'][$group] }}</summary>
        <div class="mt-3 grid gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @forelse($summary as $row)<div class="rounded-xl border p-3"><p>{{ $row->period_label }}</p><p class="font-bold">S/ {{ number_format($row->amount / 100, 2) }}</p><p class="text-sm">{{ $row->sale_count }} ventas</p></div>@empty<p>Sin ventas en este período.</p>@endforelse
        </div>
        <div class="mt-3">{{ $summary->links() }}</div>
    </details>
    <div class="overflow-x-auto rounded-2xl border bg-card">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Ventas rápidas registradas</caption>
            <thead class="bg-muted"><tr><th class="p-3">Venta</th><th class="p-3">Fecha</th><th class="p-3">Cliente</th><th class="p-3">Pago</th><th class="p-3">Total</th></tr></thead>
            <tbody class="divide-y">
                @forelse($sales as $sale)<tr><td class="p-3"><a class="text-primary underline" href="{{ route('admin.quick-sales.show', $sale) }}">#{{ $sale->id }}</a></td><td class="whitespace-nowrap p-3">{{ $sale->sold_on->format('d/m/Y') }}</td><td class="p-3">{{ $sale->customer_name ?: 'Sin nombre' }}</td><td class="p-3">{{ $sale->payment_method === 'cash' ? 'Efectivo' : 'Yape' }}</td><td class="whitespace-nowrap p-3 font-bold">S/ {{ number_format($sale->total_cents / 100, 2) }}</td></tr>@empty<tr><td colspan="5" class="p-5">No hay ventas para estos filtros.</td></tr>@endforelse
            </tbody>
        </table>
    </div>
    {{ $sales->links() }}
</div>
@endsection
