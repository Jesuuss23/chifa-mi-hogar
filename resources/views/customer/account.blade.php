<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Mi cuenta | Chifa Mi Hogar</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50">

    <main class="mx-auto max-w-5xl p-6">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-slate-900">
                    Mi cuenta
                </h1>
                <p class="mt-1 text-slate-500">
                    Hola, {{ auth()->user()->name }}
                </p>
            </div>

            <form method="POST" action="{{ route('customer.logout') }}">
                @csrf
                <button class="rounded-lg border px-4 py-2 hover:bg-white">
                    Cerrar sesión
                </button>
            </form>
        </div>

        <div class="mt-8 rounded-2xl bg-white p-6 shadow-sm">
            <h2 class="text-xl font-bold">Mis pedidos</h2>

            @forelse ($orders as $order)
                <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-b pb-4">
                    <div>
                        <p class="font-semibold">{{ $order->code }}</p>
                        <p class="text-sm text-slate-500">
                            {{ $order->created_at->format('d/m/Y H:i') }}
                        </p>
                        <p class="text-sm">
                            Estado: {{ $order->status }}
                        </p>
                    </div>

                    <div class="text-right">
                        <p class="font-bold">
                            S/ {{ number_format((float) $order->total, 2) }}
                        </p>

                    @if ($order->status === 'delivered' && ! $order->rating)
                        <form
                            action="{{ route('customer.orders.rate', $order) }}"
                            method="POST"
                            class="mt-4 rounded-xl border border-amber-200 bg-amber-50 p-4"
                        >
                            @csrf

                            <p class="mb-2 font-semibold text-slate-800">
                                ¿Cómo estuvo tu pedido?
                            </p>

                            <div class="flex flex-wrap gap-3">
                                @for ($star = 1; $star <= 5; $star++)
                                    <label class="cursor-pointer">
                                        <input
                                            type="radio"
                                            name="rating"
                                            value="{{ $star }}"
                                            required
                                            class="accent-amber-500"
                                        >
                                        <span class="text-sm">{{ $star }} ★</span>
                                    </label>
                                @endfor
                            </div>

                            <textarea
                                name="comment"
                                maxlength="1000"
                                rows="2"
                                placeholder="Cuéntanos tu experiencia (opcional)"
                                class="mt-3 w-full rounded-lg border p-3"
                            >{{ old('comment') }}</textarea>

                            @error('rating')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror

                            <button
                                type="submit"
                                class="mt-3 rounded-lg bg-red-700 px-5 py-2 font-semibold text-white"
                            >
                                Enviar valoración
                            </button>
                        </form>
                    @elseif ($order->rating)
                        <p class="mt-2 text-sm text-green-700">
                            Ya valoraste este pedido:
                            {{ $order->rating->rating }} de 5 estrellas.
                        </p>
                    @endif
                    </div>
                </div>
            @empty
                <p class="mt-5 text-slate-500">
                    Todavía no tienes pedidos vinculados a tu cuenta.
                </p>
            @endforelse

            <div class="mt-6">
                {{ $orders->links() }}
            </div>
        </div>

        <a href="{{ route('storefront.index') }}"
           class="mt-6 inline-block font-medium text-red-700">
            ← Seguir comprando
        </a>
    </main>
</body>
</html>
