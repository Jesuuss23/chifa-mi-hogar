@extends('layouts.admin')

@section('title', $combo->exists ? 'Editar combo' : 'Crear combo')
@section('page-title', $combo->exists ? 'Editar combo' : 'Crear combo')

@section('content')
@php
    $editing = $combo->exists;

    $initialItems = old('items');

    if ($initialItems === null) {
        $initialItems = $editing
            ? $combo->items->map(fn ($item) => [
                'type' => $item->product_id ? 'product' : 'extra',
                'id' => $item->product_id ?: $item->extra_id,
                'quantity' => $item->quantity,
            ])->values()->all()
            : [];
    }

    $catalog = collect($products)->map(fn ($p) => [
        'type' => 'product',
        'id' => $p->id,
        'name' => $p->name,
        'price' => (float) $p->price,
    ])->concat(
        collect($extras)->map(fn ($e) => [
            'type' => 'extra',
            'id' => $e->id,
            'name' => $e->name,
            'price' => (float) $e->price,
        ])
    )->values();
@endphp

<div class="catalog-surface admin-catalog-surface mx-auto max-w-4xl">
    <a href="{{ route('admin.combos.index') }}" class="text-primary">
        ← Volver a combos
    </a>

    <h1 class="mt-4 text-3xl font-bold">
        {{ $editing ? 'Editar combo' : 'Crear nuevo combo' }}
    </h1>

    @if ($errors->any())
        <div class="mt-5 rounded-none bg-destructive/10 p-4 text-destructive">
            <ul class="list-disc pl-5">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form
        enctype="multipart/form-data"
        method="POST"
        action="{{ $editing
            ? route('admin.combos.update', $combo)
            : route('admin.combos.store') }}"
        class="mt-6 space-y-6"
    >
        @csrf
        @if ($editing)
            @method('PUT')
        @endif

        <section class="rounded-none border bg-card p-6 shadow-storefront">
            <h2 class="mb-4 text-xl font-bold">Información del combo</h2>

            <label for="admin-combo-name" class="block text-sm font-medium">Nombre</label>
            <input
                id="admin-combo-name"
                name="name"
                required
                maxlength="150"
                value="{{ old('name', $combo->name) }}"
                class="mt-1 w-full rounded-none border p-3"
            >

            <label for="admin-combo-description" class="mt-4 block text-sm font-medium">Descripción</label>
            <textarea
                id="admin-combo-description"
                name="description"
                rows="3"
                class="mt-1 w-full rounded-none border p-3"
            >{{ old('description', $combo->description) }}</textarea>

            <label for="admin-combo-sort_order" class="mt-4 block text-sm font-medium">Orden en carta</label>
            <input
                type="number"
                min="0"
                id="admin-combo-sort_order"
                name="sort_order"
                value="{{ old('sort_order', $combo->sort_order ?? 0) }}"
                class="mt-1 w-full rounded-none border p-3"
            >

            <input type="hidden" name="show_in_menu" value="0">
            <label class="mt-4 flex items-center gap-2">
                <input
                    type="checkbox"
                    name="show_in_menu"
                    value="1"
                    @checked(old('show_in_menu', $combo->show_in_menu ?? false))
                >
                Mostrar en el carrusel de combos del catálogo
            </label>

            @if ($editing)
                <input type="hidden" name="is_available" value="0">
                <label class="mt-3 flex items-center gap-2">
                    <input
                        type="checkbox"
                        name="is_available"
                        value="1"
                        @checked(old('is_available', $combo->is_available))
                    >
                    Combo disponible
                </label>
            @endif
            <div class="mt-5">
                <label for="admin-combo-image" class="block text-sm font-medium">
                    Imagen del combo
                </label>

                @if ($combo->mediaAsset)
                    <p class="mt-3 text-xs font-semibold text-foreground/75">Imagen guardada actualmente</p>
                    <img
                        src="{{ $combo->mediaAsset->cardUrl() }}"
                        alt="{{ $combo->name }}"
                        class="mt-3 h-40 w-56 rounded-none object-cover"
                    >
                @endif

                <input
                    type="file"
                    id="admin-combo-image"
                    name="image"
                    accept="image/jpeg,image/png,image/webp"
                    class="mt-3 block w-full rounded-none border p-3"
                >
            <x-admin-image-preview input="admin-combo-image" :max-mb="5" />

                <p class="mt-2 text-xs text-foreground/75">
                    JPG, PNG o WEBP. Máximo 5 MB.
                </p>
            </div>
        </section>

        <section class="rounded-none border bg-card p-6 shadow-storefront">
            <h2 class="text-xl font-bold">Componentes del combo</h2>

            <p class="mt-1 text-sm text-foreground/75">
                Selecciona platos o adicionales y establece sus cantidades.
            </p>

            <div id="combo-items" class="mt-4 space-y-3"></div>

            <button
                type="button"
                id="add-item"
                class="mt-4 rounded-none border border-primary px-4 py-2 text-primary"
            >
                + Agregar componente
            </button>
        </section>
        <section class="rounded-none border bg-card p-6 shadow-storefront">
            <h2 class="text-xl font-bold">
                ¿En qué platos recomendar este combo?
            </h2>

            <p class="mt-2 text-sm text-foreground/75">
                Opcional. Puedes seleccionar varios platos.
                Si no seleccionas ninguno, el combo seguirá existiendo
                de forma independiente.
            </p>

            @php
                $selected = old(
                    'recommended_product_ids',
                    $selectedRecommendations
                );
            @endphp

            <div class="mt-4 grid gap-3 sm:grid-cols-2">
                @foreach ($products as $product)
                    <label class="flex items-center gap-3 rounded-none border p-3">
                        <input
                            type="checkbox"
                            name="recommended_product_ids[]"
                            value="{{ $product->id }}"
                            @checked(in_array($product->id, $selected))
                        >

                        <span>
                            {{ $product->name }}
                            <small class="block text-foreground/75">
                                S/ {{ number_format((float) $product->price, 2) }}
                            </small>
                        </span>
                    </label>
                @endforeach
            </div>
        </section>
        <section class="rounded-none border bg-card p-6 shadow-storefront">
            <h2 class="mb-4 text-xl font-bold">Configuración de precios</h2>

            <div class="flex flex-wrap justify-between gap-2 border-b pb-4">
                <span>Precio referencial del catálogo</span>
                <strong id="reference-price">S/ 0.00</strong>
            </div>

            <label for="sale-price" class="mt-4 block text-sm font-medium">
                Precio de venta del combo
            </label>

            <input
                id="sale-price"
                type="number"
                name="price"
                step="0.01"
                min="0.01"
                required
                value="{{ old('price', $combo->price) }}"
                class="mt-2 w-full rounded-none border p-3"
            >

            <p id="price-difference" class="mt-3 text-sm text-foreground/75"></p>

        </section>

        <section class="rounded-2xl border bg-card p-6 shadow-storefront">
            <h2 class="text-xl font-bold">Disponibilidad del combo</h2>
            <p class="mt-2 text-sm text-foreground/75">Horario de Perú. Fuera de los días elegidos, el combo no se muestra ni se puede comprar.</p>
            <label class="mt-4 block" for="availability-mode">Disponible</label>
            <select id="availability-mode" name="availability_mode" class="mt-2 w-full rounded-xl border bg-card p-3">
                @foreach(['always' => 'Todos los días', 'weekdays' => 'Días de la semana', 'dates' => 'Fechas concretas'] as $mode => $label)
                    <option value="{{ $mode }}" @selected(old('availability_mode', $combo->availability_mode ?? 'always') === $mode)>{{ $label }}</option>
                @endforeach
            </select>
            <fieldset class="mt-5" data-combo-schedule="weekdays">
                <legend class="font-semibold">Días de la semana</legend>
                <div class="mt-3 flex flex-wrap gap-4">
                    @foreach([1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'] as $day => $label)
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="available_weekdays[]" value="{{ $day }}" @checked(in_array($day, old('available_weekdays', $combo->available_weekdays ?? [])))>
                            {{ $label }}
                        </label>
                    @endforeach
                </div>
            </fieldset>
            <div class="mt-5" data-combo-schedule="dates">
                <label for="combo-dates" class="block font-semibold">Fechas del calendario</label>
                <p id="combo-dates-help" class="mt-2 text-sm">Una fecha por línea, con formato AAAA-MM-DD. Puedes elegir fechas de distintos meses.</p>
                <div class="my-3 flex flex-wrap items-end gap-3" data-date-picker hidden>
                    <label>Elegir fecha <input type="date" id="combo-date-picker" class="block rounded-xl border p-2"></label>
                    <button type="button" id="combo-add-date" class="rounded-full bg-primary px-4 py-2 text-primary-foreground">Agregar fecha</button>
                </div>
                <textarea id="combo-dates" name="available_dates" rows="5" aria-describedby="combo-dates-help" class="mt-2 w-full rounded-xl border bg-card p-3">{{ old('available_dates', implode("\n", $combo->available_dates ?? [])) }}</textarea>
            </div>
        </section>
        <script>
        (() => {
            const mode = document.getElementById('availability-mode');
            const refresh = () => document.querySelectorAll('[data-combo-schedule]').forEach(panel => {
                panel.hidden = panel.dataset.comboSchedule !== mode.value;
            });
            mode.addEventListener('change', refresh);
            refresh();
            document.querySelector('[data-date-picker]').hidden = false;
            document.getElementById('combo-add-date').addEventListener('click', () => {
                const picker = document.getElementById('combo-date-picker');
                const dates = document.getElementById('combo-dates');
                if (!picker.value || !picker.checkValidity()) return;
                dates.value = [...new Set([...dates.value.split(/[\s,]+/).filter(Boolean), picker.value])].sort().join('\n');
                picker.value = '';
            });
        })();
        </script>

        <button
            type="submit"
            class="w-full rounded-none bg-primary px-6 py-4 font-bold text-primary-foreground"
        >
            {{ $editing ? 'Guardar cambios' : 'Crear combo' }}
        </button>
    </form>
</div>

<script>
    const catalog = @json($catalog);
    const initialItems = @json($initialItems);

    const container = document.getElementById('combo-items');
    const referencePrice = document.getElementById('reference-price');
    const salePrice = document.getElementById('sale-price');
    const priceDifference = document.getElementById('price-difference');

    let items = initialItems.map(item => {
        const type = item.type
            || (item.product_id ? 'product' : 'extra');

        const id = Number(
            item.id ?? item.product_id ?? item.extra_id
        );

        return {
            type,
            id,
            quantity: Math.max(1, Math.min(20, Math.trunc(Number(item.quantity) || 1)))
        };
    });

    function money(value) {
        return 'S/ ' + value.toFixed(2);
    }

    function renderItems() {
        container.innerHTML = '';

        items.forEach((item, index) => {
            const row = document.createElement('div');
            row.className = 'grid gap-3 rounded-none border p-3 md:grid-cols-[1fr_auto_90px]';

            const select = document.createElement('select');
            select.setAttribute('aria-label', `Componente ${index + 1}`);
            select.className = 'w-full rounded-none border p-2';

            catalog.forEach(entry => {
                const option = document.createElement('option');
                option.value = entry.type + ':' + entry.id;
                option.textContent = `${entry.type === 'product' ? 'Plato' : 'Adicional'}: ${entry.name} — ${money(entry.price)}`;
                option.selected = entry.type === item.type && entry.id === item.id;
                select.appendChild(option);
            });

            select.addEventListener('change', () => {
                const [type, id] = select.value.split(':');
                const numericId = Number(id);

                const duplicate = items.some((item, position) =>
                    position !== index &&
                    item.type === type &&
                    item.id === numericId
                );

                if (duplicate) {
                    alert('Este componente ya está agregado. Aumenta su cantidad.');
                    renderItems();
                    return;
                }

                items[index].type = type;
                items[index].id = numericId;

                renderItems();
            });
            const quantity = document.createElement('input');
            quantity.setAttribute('aria-label', `Cantidad del componente ${index + 1}`);
            quantity.type = 'hidden';
            quantity.dataset.quantityInput = '';
            quantity.min = 1;
            quantity.max = 20;
            quantity.value = item.quantity;
            quantity.className = 'w-full rounded-none border p-2';

            quantity.addEventListener('input', () => {
                items[index].quantity = Number(quantity.value);
                calculate();
            });

            const remove = document.createElement('button');
            remove.setAttribute('aria-label', `Quitar componente ${index + 1}`);
            remove.type = 'button';
            remove.textContent = 'Quitar';
            remove.className = 'rounded-none bg-destructive/10 px-3 py-2 text-destructive';

            remove.addEventListener('click', () => {
                items.splice(index, 1);
                renderItems();
            });

            row.appendChild(select);
            const stepper = document.createElement('div');
            stepper.className = 'quantity-stepper';
            stepper.dataset.quantityStepper = '';
            stepper.setAttribute('role', 'group');
            stepper.setAttribute('aria-label', `Cantidad del componente ${index + 1}`);
            const count = document.createElement('output');
            count.textContent = item.quantity;
            count.setAttribute('aria-live', 'polite');
            const minus = document.createElement('button');
            const plus = document.createElement('button');
            [[minus, -1, 'Disminuir'], [plus, 1, 'Aumentar']].forEach(([button, step, label]) => {
                button.type = 'button';
                button.dataset.quantityStep = step;
                button.textContent = step === -1 ? '−' : '+';
                button.setAttribute('aria-label', `${label} cantidad del componente ${index + 1}`);
            });
            minus.disabled = item.quantity <= 1;
            plus.disabled = item.quantity >= 20;
            stepper.append(minus, count, quantity, plus);
            row.appendChild(stepper);
            row.appendChild(remove);

            const hiddenId = document.createElement('input');
            hiddenId.type = 'hidden';
            hiddenId.name = `items[${index}][${item.type}_id]`;
            hiddenId.value = item.id;

            const hiddenQuantity = document.createElement('input');
            hiddenQuantity.type = 'hidden';
            hiddenQuantity.name = `items[${index}][quantity]`;
            hiddenQuantity.value = item.quantity;

            quantity.addEventListener('input', () => {
                hiddenQuantity.value = quantity.value;
            });

            row.appendChild(hiddenId);
            row.appendChild(hiddenQuantity);

            container.appendChild(row);
        });

        calculate();
    }

    function calculate() {
        const total = items.reduce((sum, item) => {
            const entry = catalog.find(p =>
                p.type === item.type && p.id === item.id
            );

            return sum + (entry ? entry.price * item.quantity : 0);
        }, 0);

        referencePrice.textContent = money(total);

        const sale = Number(salePrice.value);

        if (!salePrice.value || sale <= 0) {
            priceDifference.textContent = 'Ingresa el precio de venta del combo.';
            return;
        }

        const difference = total - sale;

        priceDifference.textContent = difference >= 0
            ? `Ahorro frente a precios individuales: ${money(difference)}`
            : `Recargo frente a precios individuales: ${money(Math.abs(difference))}`;
    }

    document.getElementById('add-item').addEventListener('click', () => {
        const available = catalog.find(entry => {
            return !items.some(item =>
                item.type === entry.type &&
                item.id === entry.id
            );
        });

        if (!available) {
            alert('Ya agregaste todos los componentes disponibles.');
            return;
        }

        items.push({
            type: available.type,
            id: available.id,
            quantity: 1
        });

        renderItems();
    });

    salePrice.addEventListener('input', calculate);

    renderItems();
</script>
@endsection
