@extends('layouts.admin')

@section('content')
<div class="mx-auto max-w-7xl space-y-6 px-4 py-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold">Adicionales</h1>
            <p class="text-sm text-slate-500">
                Complementos que se ofrecen junto con los platos.
            </p>
        </div>
        <a href="{{ route('admin.extras.create') }}"
           class="rounded-xl bg-orange-600 px-5 py-3 font-semibold text-white">
            + Nuevo adicional
        </a>
    </div>

    @if(session('success'))
        <div class="rounded-xl bg-green-50 p-4 text-green-700">{{ session('success') }}</div>
    @endif
    @if($errors->any())
        <div class="rounded-xl bg-red-50 p-4 text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="overflow-x-auto rounded-2xl border bg-white shadow-sm">
        <table class="w-full min-w-[750px] text-left text-sm">
            <thead class="bg-slate-50 text-slate-600">
                <tr>
                    <th class="p-4">Imagen</th>
                    <th class="p-4">Adicional</th>
                    <th class="p-4">Categoría</th>
                    <th class="p-4">Precio</th>
                    <th class="p-4">Estado</th>
                    <th class="p-4">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y">
                @forelse($extras as $extra)
                    <tr>
                        <td class="p-4">
                            <x-catalog-image :item="$extra" variant="thumbnail"
                                class="h-16 w-16 rounded-xl object-cover" />
                        </td>
                        <td class="p-4">
                            <div class="font-semibold">{{ $extra->name }}</div>
                            <div class="text-xs text-slate-500">
                                {{ \Illuminate\Support\Str::limit($extra->description, 60) }}
                            </div>
                        </td>
                        <td class="p-4">
                            {{ $extra->extraCategory?->name ?? 'Sin categoría' }}
                        </td>
                        <td class="p-4 font-semibold">
                            S/ {{ number_format($extra->price, 2) }}
                        </td>
                        <td class="p-4">
                            <span class="rounded-full px-3 py-1 text-xs {{ $extra->is_available ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' }}">
                                {{ $extra->is_available ? 'Disponible' : 'No disponible' }}
                            </span>
                        </td>
                        <td class="p-4">
                            <div class="flex gap-2">
                                <a href="{{ route('admin.extras.edit', $extra) }}"
                                   class="rounded-lg bg-slate-900 px-3 py-2 text-white">
                                    Editar
                                </a>
                                <form method="POST"
                                      action="{{ route('admin.extras.destroy', $extra) }}"
                                      onsubmit="return confirm('¿Eliminar este adicional?')">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg border border-red-200 px-3 py-2 text-red-600">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="p-10 text-center text-slate-500">
                            No hay adicionales registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $extras->links() }}
</div>
@endsection