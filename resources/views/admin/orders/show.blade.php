@extends('layouts.admin')

@section('page-title', 'Detalle del pedido')

@section('content')
<div class="catalog-surface admin-orders-surface max-w-6xl mx-auto space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-4">
        <div>
            <a href="{{ route('admin.orders.index') }}"
               class="text-sm text-primary hover:underline">
                ← Volver a pedidos
            </a>
            <h1 class="text-2xl font-bold mt-2">
                Pedido {{ $order->code }}
            </h1>
            <p class="text-sm text-foreground/75">
                {{ $order->created_at->format('d/m/Y H:i') }}
            </p>
        </div>
        <div class="rounded-none bg-accent px-4 py-2 font-semibold">
            {{ $statuses[$order->status] ?? $order->status }}
        </div>
    </div>

    @if($errors->any())
        <div class="rounded-none bg-destructive/10 text-destructive p-4">
            <ul class="list-disc pl-5">
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <div class="grid lg:grid-cols-3 gap-6">
        <div class="lg:col-span-2 space-y-6">
            <section class="rounded-none bg-card border p-5">
                <h2 class="text-lg font-bold mb-4">Datos del cliente</h2>

                <dl class="grid sm:grid-cols-2 gap-4 text-sm">
                    <div>
                        <dt class="text-foreground/75">Nombre</dt>
                        <dd class="font-semibold">{{ $order->customer_name }}</dd>
                    </div>
                    <div>
                        <dt class="text-foreground/75">Teléfono</dt>
                        <dd>{{ $order->customer_phone }}</dd>
                    </div>
                    <div>
                        <dt class="text-foreground/75">Correo</dt>
                        <dd>{{ $order->customer_email ?: 'No proporcionado' }}</dd>
                    </div>
                    <div>
                        <dt class="text-foreground/75">Tipo de entrega</dt>
                        <dd>{{ $order->delivery_type === 'pickup' ? 'Recojo en local' : 'Delivery' }}</dd>
                    </div>
                </dl>

                @if($order->delivery_type === 'delivery')
                    <div class="mt-5 border-t pt-4 text-sm space-y-2">
                        <p><strong>Dirección:</strong> {{ $order->delivery_address }}</p>
                        <p><strong>Referencia:</strong> {{ $order->delivery_reference ?: 'Sin referencia' }}</p>
                        <p><strong>Distancia:</strong> {{ $order->delivery_distance_km ?? 'Sin calcular' }} km</p>

                        @if($order->delivery_latitude !== null && $order->delivery_longitude !== null)
                            <a
                                href="https://www.google.com/maps/search/?api=1&query={{ urlencode($order->delivery_latitude . ',' . $order->delivery_longitude) }}"
                                target="_blank"
                                rel="noopener noreferrer"
                                class="inline-block text-primary font-semibold hover:underline"
                            >
                                Ver ubicación en Google Maps ↗
                            </a>
                        @endif
                    </div>
                @endif

                @if($order->customer_notes)
                    <p class="mt-4 text-sm">
                        <strong>Observaciones:</strong> {{ $order->customer_notes }}
                    </p>
                @endif
            </section>

            <section class="rounded-none bg-card border p-5">
                <h2 class="text-lg font-bold mb-4">Detalle de productos</h2>

                <div class="divide-y">
                    @foreach($order->items as $item)
                        <div class="py-4">
                            <div class="flex justify-between gap-4">
                                <div>
                                    <p class="font-semibold">
                                        {{ $item->quantity }} × {{ $item->item_name }}
                                    </p>
                                    <p class="text-xs text-foreground/75">
                                        {{ $item->item_type === 'recommendation' ? 'Recomendación' : 'Producto' }}
                                    </p>
                                </div>
                                <p class="font-semibold">
                                    S/ {{ number_format((float) $item->line_total, 2) }}
                                </p>
                            </div>

                            @if($item->extras->isNotEmpty())
                                <div class="ml-4 mt-2 text-sm text-foreground/75">
                                    <p class="font-medium">Extras por unidad:</p>
                                    @foreach($item->extras as $extra)
                                        <p>
                                            + {{ $extra->quantity }} × {{ $extra->extra_name }}
                                            (S/ {{ number_format((float) $extra->line_total, 2) }})
                                        </p>
                                    @endforeach
                                </div>
                            @endif

                            @if($item->notes)
                                <p class="text-sm text-foreground/75 mt-2">
                                    Nota: {{ $item->notes }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="border-t pt-4 mt-4 space-y-2 text-sm">
                    <div class="flex justify-between">
                        <span>Subtotal</span>
                        <span>S/ {{ number_format((float) $order->subtotal, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Delivery</span>
                        <span>S/ {{ number_format((float) $order->delivery_fee, 2) }}</span>
                    </div>
                    <div class="flex justify-between">
                        <span>Descuento</span>
                        <span>- S/ {{ number_format((float) $order->discount_amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between text-lg font-bold border-t pt-3">
                        <span>Total</span>
                        <span>S/ {{ number_format((float) $order->total, 2) }}</span>
                    </div>
                </div>
            </section>

            <section class="rounded-none bg-card border p-5">
                <h2 class="text-lg font-bold mb-4">Historial de estados</h2>

                <div class="space-y-4">
                    @forelse($order->statusHistories->sortBy('created_at') as $history)
                        <div class="border-l-2 border-primary pl-4">
                            <p class="font-semibold text-sm">
                                {{ $statuses[$history->from_status] ?? 'Inicio' }}
                                →
                                {{ $statuses[$history->to_status] ?? $history->to_status }}
                            </p>
                            <p class="text-xs text-foreground/75">
                                {{ $history->created_at->format('d/m/Y H:i') }}
                                · {{ $history->changedBy?->name ?? 'Sistema' }}
                            </p>
                            @if($history->comment)
                                <p class="text-sm mt-1">{{ $history->comment }}</p>
                            @endif
                        </div>
                    @empty
                        <p class="text-sm text-foreground/75">
                            Aún no hay cambios registrados.
                        </p>
                    @endforelse
                </div>
            </section>
        </div>

        <div class="space-y-6">
            <section class="rounded-none bg-card border p-5">
                <h2 class="text-lg font-bold mb-4">Verificación de pago</h2>

                <p class="text-sm mb-2">
                    <strong>Método:</strong> {{ strtoupper($order->payment_method) }}
                </p>
                <p class="text-sm mb-4">
                    <strong>Estado:</strong> {{ $order->payment_status }}
                </p>
                @if($order->payment_method === 'cash')

                    <div class="rounded-none bg-amber-50 p-4 space-y-3">
                        <h3 class="font-bold text-amber-900">
                            💵 Pago contra entrega
                        </h3>

                        <p class="text-sm">
                            Modalidad:
                            <strong>
                                {{ $order->cash_payment_type === 'exact'
                                    ? 'Pago exacto'
                                    : 'Necesita vuelto' }}
                            </strong>
                        </p>

                        <p class="text-sm">
                            Total del pedido:
                            <strong>
                                S/ {{ number_format((float) $order->total, 2) }}
                            </strong>
                        </p>

                        <p class="text-sm">
                            El cliente pagará con:
                            <strong>
                                S/ {{ number_format((float) $order->cash_received_amount, 2) }}
                            </strong>
                        </p>

                        <p class="text-sm">
                            Vuelto a entregar:
                            <strong class="text-lg text-primary">
                                S/ {{ number_format((float) $order->cash_change_amount, 2) }}
                            </strong>
                        </p>

                        @if($order->payment_status === 'verified')
                            <div class="rounded-none bg-green-100 p-3 text-green-800">
                                ✓ Efectivo cobrado y registrado.
                            </div>
                        @elseif(in_array($order->status, ['confirmed', 'preparing', 'on_the_way']))
                            <form
                                method="POST"
                                action="{{ route('admin.orders.collect-cash', $order) }}"
                                class="space-y-3"
                            >
                                @csrf
                                @method('PATCH')

                                <label class="flex items-start gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="confirm_collection"
                                        value="1"
                                        required
                                    >
                                    <span>
                                        Confirmo que recibí el efectivo del cliente
                                        y entregué el vuelto correspondiente.
                                    </span>
                                </label>

                                <button
                                    type="submit"
                                    class="w-full rounded-none bg-green-700 text-white py-3 font-semibold"
                                >
                                    Registrar efectivo recibido
                                </button>
                            </form>
                        @else
                            <p class="text-sm text-amber-800">
                                Primero confirma el pedido para habilitar
                                el registro del cobro.
                            </p>
                        @endif
                    </div>

                @elseif(in_array($order->payment_method, ['yape', 'plin']))

                    @if($order->payment_status === 'verified')
                        <p class="rounded-none bg-green-50 p-3 text-green-800">
                            ✓ Pago digital verificado.
                        </p>
                    @elseif(!in_array($order->status, ['cancelled', 'delivered']))
                        <form
                            method="POST"
                            action="{{ route('admin.orders.payment', $order) }}"
                            class="space-y-3"
                        >
                            @csrf
                            @method('PATCH')

                            <select
                                name="decision"
                                aria-label="Decisión sobre el pago"
                                required
                                class="w-full rounded-none border-input"
                            >
                                <option value="">Seleccionar decisión</option>
                                <option value="verified">Verificar pago</option>

                                @if($order->status === 'pending')
                                    <option value="rejected">Rechazar pago</option>
                                @endif
                            </select>

                            <button
                                type="submit"
                                class="w-full rounded-none bg-green-700 text-white py-3"
                            >
                                Guardar decisión
                            </button>
                        </form>
                    @endif

                @endif

                <p class="text-xs text-foreground/75 mt-3">
                    Verifica el abono real en Yape o Plin antes de aprobarlo.
                    Un comprobante por sí solo no demuestra que el dinero llegó.
                </p>
            </section>
            @if(
                $order->delivery_type === 'delivery'
                && in_array($order->status, ['confirmed', 'preparing'], true)
            )
                <section class="rounded-none bg-card border p-5">
                    <h2 class="text-lg font-bold mb-4">
                        Asignar repartidor
                    </h2>

                    <form
                        method="POST"
                        action="{{ route('admin.orders.assign-driver', $order) }}"
                        class="space-y-4">

                        @csrf
                        @method('PATCH')

                        <select
                            name="delivery_user_id"
                            aria-label="Repartidor asignado"
                            class="w-full rounded-none border-input"
                            required>

                            <option value="">Seleccionar repartidor</option>

                            @foreach($drivers as $driver)
                                <option
                                    value="{{ $driver->id }}"
                                    @selected(
                                        old(
                                            'delivery_user_id',
                                            $order->delivery_user_id
                                        ) == $driver->id
                                    )>
                                    {{ $driver->name }}
                                </option>
                            @endforeach
                        </select>

                        @error('delivery_user_id')
                            <p class="text-sm text-destructive">
                                {{ $message }}
                            </p>
                        @enderror

                        <button
                            type="submit"
                            class="rounded-none bg-primary px-5 py-3 text-primary-foreground">
                            Guardar asignación
                        </button>
                    </form>
                </section>
            @endif
            <section class="rounded-none bg-card border p-5">
                <h2 class="text-lg font-bold mb-4">Actualizar estado</h2>

                @if(count($nextStatuses) > 0)
                    <form method="POST"
                          action="{{ route('admin.orders.status', $order) }}"
                          class="space-y-4">
                        @csrf
                        @method('PATCH')

                        <div>
                            <label for="order-status" class="block text-sm font-medium mb-1">
                                Nuevo estado
                            </label>
                            <select name="status" id="order-status"
                                    class="w-full rounded-none border-input"
                                    required>
                                <option value="">Seleccionar</option>
                                @foreach($nextStatuses as $status)
                                    <option value="{{ $status }}">
                                        {{ $statuses[$status] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <div>
                            <label for="order-comment" class="block text-sm font-medium mb-1">
                                Comentario
                            </label>
                            <textarea id="order-comment" name="comment" rows="2"
                                      class="w-full rounded-none border-input"></textarea>
                        </div>

                        <div>
                            <label for="order-cancellation-reason" class="block text-sm font-medium mb-1">
                                Motivo de cancelación (obligatorio al cancelar)
                            </label>
                            <textarea id="order-cancellation-reason" name="cancellation_reason" rows="3"
                                      class="w-full rounded-none border-input"></textarea>
                        </div>

                        <button type="submit"
                                class="w-full rounded-none bg-primary text-primary-foreground py-2 font-semibold">
                            Actualizar pedido
                        </button>
                    </form>
                @else
                    <p class="text-sm text-foreground/75">
                        Este pedido ya no admite cambios de estado.
                    </p>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection