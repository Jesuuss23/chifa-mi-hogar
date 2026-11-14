@extends('layouts.admin')
@section('page-title', 'Nueva venta rápida')
@section('content')
@php
    $oldText = fn ($key, $default = '') => is_scalar(old($key, $default)) ? (string) old($key, $default) : $default;
    $key = $oldText('submission_key');
    if (!\Illuminate\Support\Str::isUuid($key)) $key = (string) \Illuminate\Support\Str::uuid();
@endphp
<div class="quick-sale mx-auto max-w-4xl space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-bold">Nueva venta</h1>
        <a href="{{ route('admin.quick-sales.index') }}" class="text-primary underline">Ver registros</a>
    </div>
    @if($errors->any())
        <div role="alert" class="rounded-2xl border border-destructive bg-card p-4"><ul class="list-inside list-disc">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
    @endif
    <form method="POST" action="{{ route('admin.quick-sales.store') }}" data-quick-sale class="space-y-5">
        @csrf
        <input type="hidden" name="submission_key" value="{{ $key }}">
        <script type="application/json" data-sale-catalog>@json($catalog)</script>
        <script type="application/json" data-sale-initial>@json(old('items', []))</script>
        <section class="rounded-2xl border bg-card p-5">
            <h2 class="mb-3 text-lg font-bold">Platos y adicionales</h2>
            <div class="flex flex-wrap items-end gap-3">
                <label class="min-w-0 flex-1" for="sale-item">Seleccionar
                    <select id="sale-item" data-sale-choice class="mt-1 w-full rounded-xl border p-3">
                        <option value="">Elige un plato o adicional</option>
                        @foreach(['product' => 'Platos', 'extra' => 'Adicionales'] as $type => $label)
                            <optgroup label="{{ $label }}">
                                @foreach($catalog->where('type', $type) as $item)
                                    <option value="{{ $type }}:{{ $item['id'] }}">{{ $item['name'] }} · S/ {{ number_format($item['price_cents'] / 100, 2) }}</option>
                                @endforeach
                            </optgroup>
                        @endforeach
                    </select>
                </label>
                <button type="button" data-sale-add class="rounded-full bg-primary px-5 py-3 font-semibold text-primary-foreground">+ Agregar</button>
            </div>
            <div data-sale-lines class="mt-4 space-y-3" aria-label="Detalle de venta"></div>
            <p data-sale-empty class="mt-4 text-sm text-muted-foreground">Agrega los platos y adicionales vendidos.</p>
            <p class="mt-4 text-right text-sm">Según carta: <strong data-sale-subtotal>S/ 0.00</strong></p>
        </section>
        <section class="rounded-2xl border bg-card p-5">
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label for="sale-total" class="block text-lg font-bold">Total cobrado (S/)</label>
                    <input id="sale-total" data-sale-total name="total" inputmode="decimal" type="number" min="0" max="99999.99" step="0.01" value="{{ $oldText('total') }}" class="mt-2 w-full rounded-xl border p-3 text-2xl font-bold">
                    <button type="button" data-sale-reset class="mt-2 text-sm text-primary underline">Usar total de la carta</button>
                </div>
                <fieldset>
                    <legend class="mb-2 font-bold">Método de pago</legend>
                    <div class="flex gap-3">
                        <label class="flex flex-1 items-center justify-center gap-2 rounded-xl border p-4"><input type="radio" name="payment_method" value="cash" @checked($oldText('payment_method', 'cash') === 'cash') required> Efectivo</label>
                        <label class="flex flex-1 items-center justify-center gap-2 rounded-xl border p-4"><input type="radio" name="payment_method" value="yape" @checked($oldText('payment_method') === 'yape') required> Yape</label>
                    </div>
                </fieldset>
                <label for="sale-customer" class="sm:col-span-2">Nombre del cliente <span class="text-muted-foreground">(opcional)</span>
                    <input id="sale-customer" name="customer_name" maxlength="120" value="{{ $oldText('customer_name') }}" class="mt-1 w-full rounded-xl border p-3" autocomplete="off">
                </label>
            </div>
            <details class="mt-4">
                <summary class="cursor-pointer text-sm">Fecha de venta</summary>
                <label for="sale-date" class="mt-3 block text-sm">Fecha en Lima</label>
                <input id="sale-date" name="sold_on" type="date" required min="2000-01-01" max="{{ now('America/Lima')->toDateString() }}" value="{{ $oldText('sold_on', now('America/Lima')->toDateString()) }}" class="mt-1 rounded-xl border p-3">
            </details>
        </section>
        <p role="status" data-sale-message class="text-sm"></p>
        <noscript><p>Activa JavaScript para seleccionar los platos y guardar esta venta.</p></noscript>
        <button type="submit" data-sale-save disabled class="w-full rounded-full bg-primary px-6 py-4 text-lg font-bold text-primary-foreground disabled:opacity-50">Guardar venta</button>
    </form>
</div>
@endsection
