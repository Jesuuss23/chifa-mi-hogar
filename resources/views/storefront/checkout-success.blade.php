@extends('layouts.storefront')

@section('title', 'Pedido registrado | Chifa Mi Hogar')

@section('content')
<section class="mx-auto max-w-2xl px-4 py-12">

    <div class="rounded-3xl bg-white p-6 shadow-sm sm:p-10">

        <div class="mb-6 text-center">
            <div class="mb-3 text-5xl">✅</div>

            <h1 class="text-3xl font-black text-gray-900">
                ¡Pedido registrado!
            </h1>

            <p class="mt-3 text-gray-600">
                Hemos recibido tu pedido correctamente.
            </p>
        </div>

        <div class="space-y-4 rounded-2xl bg-gray-50 p-5">

            <div class="flex flex-wrap justify-between gap-2">
                <span class="text-gray-600">Código de pedido</span>
                <strong class="break-all">{{ $order->code }}</strong>
            </div>

            <div class="flex justify-between gap-2">
                <span class="text-gray-600">Cliente</span>
                <strong>{{ $order->customer_name }}</strong>
            </div>

            <div class="flex justify-between gap-2">
                <span class="text-gray-600">Productos</span>
                <strong>S/ {{ number_format((float) $order->subtotal, 2) }}</strong>
            </div>

            <div class="flex justify-between gap-2">
                <span class="text-gray-600">Delivery</span>
                <strong>S/ {{ number_format((float) $order->delivery_fee, 2) }}</strong>
            </div>

            <hr>

            <div class="flex justify-between gap-2 text-xl">
                <strong>Total</strong>
                <strong class="text-[#982E26]">
                    S/ {{ number_format((float) $order->total, 2) }}
                </strong>
            </div>

            <div class="flex justify-between gap-2">
                <span class="text-gray-600">Método de pago</span>
                <strong>{{ strtoupper($order->payment_method) }}</strong>
            </div>

            <div class="flex justify-between gap-2">
                <span class="text-gray-600">Estado</span>
                <strong id="order-status" class="text-amber-700">
                    {{ match($order->status) {
                        'pending' => 'Pedido recibido',
                        'confirmed' => 'Confirmado',
                        'preparing' => 'Preparando',
                        'on_the_way' => 'En camino',
                        'delivered' => 'Entregado',
                        'cancelled' => 'Cancelado',
                        default => 'En seguimiento'
                    } }}
                </strong>
            </div>
        </div>

        {{-- INFORMACIÓN DEL PAGO --}}

        @if(in_array($order->payment_method, ['yape', 'plin']))

            {{-- Pago digital pendiente --}}
            <div
                id="payment-instructions"
                class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-5"
                @if($order->payment_status === 'verified') hidden @endif
            >
                <h2 class="text-lg font-bold text-green-900">
                    Enviar comprobante de pago
                </h2>

                <p class="mt-2 text-sm text-gray-700">
                    Si ya realizaste el pago, envía una captura
                    de tu comprobante al WhatsApp comercial.
                    El pago será verificado por el negocio.
                </p>

                @if($whatsappUrl)
                    <a
                        href="{{ $whatsappUrl }}"
                        target="_blank"
                        rel="noopener noreferrer"
                        class="mt-4 block rounded-xl bg-green-700 px-5 py-4 text-center font-bold text-white"
                    >
                        📲 Enviar comprobante por WhatsApp
                    </a>
                @else
                    <p class="mt-4 text-sm font-medium text-red-700">
                        El WhatsApp comercial todavía no está configurado.
                        Comunícate con el negocio para verificar tu pago.
                    </p>
                @endif

                <p class="mt-3 text-xs text-gray-600">
                    WhatsApp abrirá un mensaje preparado.
                    Deberás adjuntar y enviar tu comprobante manualmente.
                </p>
            </div>

            {{-- Pago digital verificado --}}
            <div
                id="payment-verified"
                class="mt-6 rounded-2xl border border-green-200 bg-green-50 p-5"
                @if($order->payment_status !== 'verified') hidden @endif
            >
                <h2 class="font-bold text-green-900">
                    ✓ Pago verificado
                </h2>

                <p class="mt-2 text-sm text-gray-700">
                    Hemos confirmado tu pago correctamente.
                    Puedes seguir el estado de tu pedido a continuación.
                </p>
            </div>

        @elseif($order->payment_method === 'cash')

            {{-- Pago en efectivo --}}
            <div class="mt-6 rounded-2xl bg-amber-50 p-5">

                <h2 class="font-bold text-amber-900">
                    Pago en efectivo
                </h2>

                <p class="mt-2 text-sm text-gray-700">
                    Tu pedido quedó registrado para pago en efectivo.
                    No necesitas enviar comprobante.
                </p>

                @if($order->cash_payment_type === 'change')

                    <p class="mt-3 text-sm">
                        Pagas con:
                        <strong>
                            S/ {{ number_format((float) $order->cash_received_amount, 2) }}
                        </strong>
                    </p>

                    <p class="text-sm">
                        Vuelto:
                        <strong>
                            S/ {{ number_format((float) $order->cash_change_amount, 2) }}
                        </strong>
                    </p>

                @endif

            </div>

        @endif
        {{-- SEGUIMIENTO DEL PEDIDO --}}

        <section class="mt-6 rounded-2xl border border-gray-200 bg-white p-5">

            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-xl font-bold text-gray-900">
                    Seguimiento de tu pedido
                </h2>

                <span id="tracking-status"
                    class="rounded-full bg-amber-100 px-3 py-1 text-sm font-bold text-amber-800">
                    {{ match($order->status) {
                        'pending' => 'Recibido',
                        'confirmed' => 'Confirmado',
                        'preparing' => 'Preparando',
                        'on_the_way' => 'En camino',
                        'delivered' => 'Entregado',
                        'cancelled' => 'Cancelado',
                        default => 'En seguimiento'
                    } }}
                </span>
            </div>

            <div id="tracking-steps" class="space-y-5">

                @foreach([
                    'pending' => 'Pedido recibido',
                    'confirmed' => 'Pedido confirmado',
                    'preparing' => 'Preparando tu comida',
                    'on_the_way' => 'En camino',
                    'delivered' => 'Pedido entregado',
                ] as $key => $label)

                    <div class="flex items-center gap-3"
                        data-tracking-step="{{ $key }}">

                        <span data-step-icon
                            class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400">
                            ○
                        </span>

                        <span data-step-label class="text-gray-500">
                            {{ $label }}
                        </span>

                    </div>

                @endforeach

            </div>

            <div id="tracking-cancelled"
                class="mt-4 rounded-xl bg-red-50 p-4 text-sm font-medium text-red-700"
                hidden>
                Este pedido fue cancelado.
            </div>

            <p class="mt-5 text-xs text-gray-500">
                El seguimiento se actualiza automáticamente mientras
                mantengas esta página abierta.
            </p>

        </section>
        {{-- ENLACE PRIVADO DE SEGUIMIENTO --}}

        <div class="mt-6 rounded-2xl border border-gray-200 bg-gray-50 p-5">

            <h2 class="text-lg font-bold text-gray-900">
                Guarda el seguimiento de tu pedido
            </h2>

            <p class="mt-2 text-sm text-gray-600">
                Podrás consultar el estado de tu pedido desde otro
                dispositivo, sin iniciar sesión.
            </p>

            <a
                href="{{ route('orders.tracking.show', ['token' => $order->tracking_token]) }}"
                class="mt-4 block rounded-xl bg-[#982E26] px-5 py-3 text-center font-bold text-white"
            >
                Ver seguimiento de mi pedido
            </a>

            <button
                type="button"
                id="copy-tracking-link"
                class="mt-3 w-full rounded-xl border border-gray-300 px-5 py-3 font-bold text-gray-800"
            >
                Copiar enlace privado
            </button>

            <p id="copy-tracking-feedback"
            class="mt-2 text-center text-xs text-gray-500"
            aria-live="polite">
                No compartas este enlace públicamente.
            </p>
        </div>

        <script>
        document.getElementById('copy-tracking-link')
            ?.addEventListener('click', async () => {
                const url = @json(route('orders.tracking.show', ['token' => $order->tracking_token]));

                const feedback = document.getElementById('copy-tracking-feedback');

                try {
                    await navigator.clipboard.writeText(url);

                    if (feedback) {
                        feedback.textContent = 'Enlace copiado correctamente.';
                    }
                } catch (error) {
                    if (feedback) {
                        feedback.textContent = 'No se pudo copiar. Abre el seguimiento y copia la URL.';
                    }
                }
            });
        </script>
        <a href="{{ url('/') }}"
           class="mt-6 block rounded-xl border border-gray-300 px-5 py-3 text-center font-bold text-gray-800">
            Volver a la tienda
        </a>

    </div>
</section>
<script>
document.addEventListener('DOMContentLoaded', () => {
    const labels = {
        pending: 'Pedido recibido',
        confirmed: 'Confirmado',
        preparing: 'Preparando',
        on_the_way: 'En camino',
        delivered: 'Entregado',
        cancelled: 'Cancelado'
    };

    const statusElement = document.getElementById('order-status');

    const trackingOrder = [
        'pending',
        'confirmed',
        'preparing',
        'on_the_way',
        'delivered'
    ];

    function updateTracking(status) {
        const currentIndex = trackingOrder.indexOf(status);
        const cancelled = status === 'cancelled';

        const badge = document.getElementById('tracking-status');
        const cancelledBox = document.getElementById('tracking-cancelled');

        const labels = {
            pending: 'Recibido',
            confirmed: 'Confirmado',
            preparing: 'Preparando',
            on_the_way: 'En camino',
            delivered: 'Entregado',
            cancelled: 'Cancelado'
        };

        if (badge) {
            badge.textContent = labels[status] ?? 'En seguimiento';
            badge.className = cancelled
                ? 'rounded-full bg-red-100 px-3 py-1 text-sm font-bold text-red-700'
                : 'rounded-full bg-green-100 px-3 py-1 text-sm font-bold text-green-800';
        }

        if (cancelledBox) {
            cancelledBox.hidden = !cancelled;
        }

        document.querySelectorAll('[data-tracking-step]').forEach(step => {
            const key = step.dataset.trackingStep;
            const index = trackingOrder.indexOf(key);

            const icon = step.querySelector('[data-step-icon]');
            const label = step.querySelector('[data-step-label]');

            const completed = !cancelled
                && currentIndex >= 0
                && index <= currentIndex;

            const active = !cancelled && index === currentIndex;

            if (icon) {
                icon.textContent = completed ? '✓' : '○';

                icon.className = completed
                    ? 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-green-100 font-bold text-green-700'
                    : 'flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-gray-400';
            }

            if (label) {
                label.className = active
                    ? 'font-bold text-green-800'
                    : completed
                        ? 'font-medium text-gray-900'
                        : 'text-gray-500';
            }
        });
    }

    async function refreshOrderStatus() {
        if (document.hidden) return;

        try {
            const response = await fetch(
                @json(route('cart.checkout.status')),
                {
                    headers: { 'Accept': 'application/json' },
                    credentials: 'same-origin',
                    cache: 'no-store'
                }
            );

            if (!response.ok) return;
            const order = await response.json();
            updateTracking(order.status);
            if (statusElement) {
                statusElement.textContent =
                    labels[order.status] ?? 'En seguimiento';
            }

            const paymentBox = document.getElementById('payment-instructions');
            const verifiedBox = document.getElementById('payment-verified');

            if (order.payment_status === 'verified') {
                if (paymentBox) {
                    paymentBox.hidden = true;
                }

                if (verifiedBox) {
                    verifiedBox.hidden = false;
                }
            } else {
                if (paymentBox) {
                    paymentBox.hidden = false;
                }

                if (verifiedBox) {
                    verifiedBox.hidden = true;
                }
            }

        } catch (error) {
            console.warn('No se pudo actualizar el seguimiento.');
        }
    }
    updateTracking(@json($order->status));
    refreshOrderStatus();

    setInterval(refreshOrderStatus, 15000);
});
</script>
@endsection
