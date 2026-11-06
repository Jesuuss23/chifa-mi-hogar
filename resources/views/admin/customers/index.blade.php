@extends('layouts.admin')
@section('title', 'Clientes registrados')
@section('page-title', 'Clientes registrados')
@section('content')
<div class="catalog-surface admin-catalog-surface mx-auto max-w-6xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Clientes registrados</h1>
        <p class="mt-2 text-sm text-foreground/75">Consulta los datos de las cuentas de clientes. Las compras como invitado se consultan en Pedidos.</p>
    </div>
    <form method="GET" action="{{ route('admin.customers.index') }}" class="space-y-3 border border-border bg-card p-5">
        <label for="customer-search" class="block font-semibold">Buscar por nombre, correo o teléfono</label>
        <div class="flex flex-wrap gap-3">
            <input id="customer-search" type="search" name="q" maxlength="120" value="{{ $search }}"
                   class="min-w-0 flex-1 border border-input bg-background p-3"
                   aria-invalid="{{ $errors->has('q') ? 'true' : 'false' }}"
                   @if($errors->has('q')) aria-describedby="customer-search-error" @endif>
            <button class="bg-primary px-5 py-3 font-semibold text-primary-foreground">Buscar</button>
            <a href="{{ route('admin.customers.index') }}" class="border border-border px-5 py-3">Limpiar</a>
        </div>
        @error('q') <p id="customer-search-error" role="alert" class="text-sm text-destructive">{{ $message }}</p> @enderror
    </form>
    <p class="text-sm">{{ $customers->total() }} {{ $customers->total() === 1 ? 'cliente encontrado' : 'clientes encontrados' }}</p>
    <div class="overflow-x-auto border border-border bg-card">
        <table class="w-full min-w-[640px] text-left text-sm">
            <caption class="sr-only">Clientes y datos de contacto registrados</caption>
            <thead class="bg-muted">
                <tr>
                    <th scope="col" class="p-4">Cliente</th>
                    <th scope="col" class="p-4">Correo</th>
                    <th scope="col" class="p-4">Teléfono</th>
                    <th scope="col" class="p-4">Registro</th>
                    <th scope="col" class="p-4">Ficha</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-border">
                @forelse($customers as $customer)
                    <tr>
                        <th scope="row" class="max-w-xs break-words p-4 font-semibold">{{ $customer->name }}</th>
                        <td class="max-w-xs break-all p-4">{{ $customer->email }}</td>
                        <td class="p-4">{{ $customer->customerProfile?->phone ?: 'Sin registrar' }}</td>
                        <td class="whitespace-nowrap p-4">{{ $customer->created_at?->format('d/m/Y') }}</td>
                        <td class="p-4"><a href="{{ route('admin.customers.show', $customer->id) }}" class="inline-block py-2 font-semibold text-primary underline">Ver datos<span class="sr-only"> de {{ $customer->name }}</span></a></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="p-6 text-center">No hay clientes para esta búsqueda.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $customers->links() }}
</div>
@endsection
