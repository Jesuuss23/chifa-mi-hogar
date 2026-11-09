@php($orderSnapshots = $liveOrders->map(fn ($order) => $order->only(['id', 'status', 'payment_status']))->values())
<div data-order-updates data-url="{{ route('admin.orders.updates') }}"
     data-orders='@json($orderSnapshots)'>
    <p data-order-update-notice hidden role="status" class="rounded-xl border border-border bg-accent p-3 text-sm">
        Hay cambios en los pedidos.
        <a href="{{ request()->fullUrl() }}" class="font-bold text-primary underline">Actualizar vista</a>
        <span>Se conservará este formulario hasta que actualices.</span>
    </p>
</div>
