<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Combos | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 p-6">

<main class="mx-auto max-w-6xl">

    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.dashboard') }}"
               class="text-sm text-red-700">
                ← Volver al panel
            </a>

            <h1 class="mt-3 text-3xl font-bold">Gestión de combos</h1>
            <p class="text-slate-500">
                Crea ofertas utilizando platos y adicionales existentes.
            </p>
        </div>

        <a href="{{ route('admin.combos.create') }}"
           class="rounded-lg bg-red-700 px-5 py-3 font-semibold text-white">
            + Crear combo
        </a>
    </div>

    @if (session('success'))
        <div class="mt-5 rounded-lg bg-green-50 p-4 text-green-800">
            {{ session('success') }}
        </div>
    @endif

    <div class="mt-6 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-left text-sm">
            <thead class="bg-slate-100">
                <tr>
                    <th class="p-4">Combo</th>
                    <th class="p-4">Referencial</th>
                    <th class="p-4">Venta</th>
                    <th class="p-4">En carta</th>
                    <th class="p-4">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($combos as $combo)
                    <tr class="border-t">
                        <td class="p-4">
                            <p class="font-semibold">{{ $combo->name }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $combo->items->count() }} componentes
                            </p>
                        </td>

                        <td class="p-4">
                            S/ {{ number_format($combo->reference_price, 2) }}
                        </td>

                        <td class="p-4 font-semibold">
                            S/ {{ number_format((float) $combo->price, 2) }}
                        </td>

                        <td class="p-4">
                            {{ $combo->show_in_menu ? 'Sí' : 'No' }}
                        </td>

                        <td class="p-4">
                            <a href="{{ route('admin.combos.edit', $combo) }}"
                               class="font-medium text-red-700">
                                Editar
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="5" class="p-8 text-center text-slate-500">
                            Todavía no hay combos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-5">
        {{ $combos->links() }}
    </div>
</main>

</body>
</html>
