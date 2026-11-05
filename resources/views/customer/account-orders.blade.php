        <section id="account-orders" class="account-orders" aria-labelledby="orders-title" tabindex="-1">
            <div class="account-section-heading"><div><p class="account-eyebrow">TU HISTORIAL</p><h2 id="orders-title" class="text-xl font-bold">Mis pedidos</h2></div><span class="account-count">{{ $orders->total() }} en total</span></div>

            @forelse ($orders as $order)
                <div class="account-order">
                    <div>
                        <p class="font-semibold">{{ $order->code }}</p>
                        <p class="text-sm text-foreground/75">
                            {{ $order->created_at->format('d/m/Y H:i') }}
                        </p>
                        <p class="text-sm">
                            Estado: <span class="account-order-status">{{ $accountStatusLabels[$order->status] ?? $order->status }}</span>
                        </p>
                    </div>

                    <div class="account-order-actions">
                        <p class="font-bold">
                            S/ {{ number_format((float) $order->total, 2) }}
                        </p>

                    @if ($order->status === 'delivered' && ! $order->rating)
                        <form
                            action="{{ route('customer.orders.rate', $order) }}"
                            method="POST"
                            class="mt-4 border border-border bg-muted p-4"
                        >
                            @csrf

                            <fieldset><legend class="mb-2 font-semibold text-foreground">¿Cómo estuvo tu pedido?</legend>

                            <div class="flex flex-wrap gap-3">
                                @for ($star = 1; $star <= 5; $star++)
                                    <label class="account-rating-option">
                                        <input
                                            type="radio"
                                            name="rating"
                                            value="{{ $star }}"
                                            required
                                            class="accent-primary"
                                        >
                                        <span class="text-sm">{{ $star }} ★</span>
                                    </label>
                                @endfor
                            </div>

                            </fieldset>
                            <label for="rating-comment-{{ $order->id }}" class="mt-3 block text-sm font-semibold">Comentario (opcional)</label>
                            <textarea id="rating-comment-{{ $order->id }}"
                                name="comment"
                                maxlength="1000"
                                rows="2"
                                placeholder="Cuéntanos tu experiencia (opcional)"
                                class="mt-3 w-full rounded-none border border-input bg-card text-foreground p-3"
                            >{{ old('comment') }}</textarea>

                            @error('rating')
                                <p role="alert" class="mt-2 text-sm text-destructive">{{ $message }}</p>
                            @enderror
                            @error('comment')
                                <p role="alert" class="mt-2 text-sm text-destructive">{{ $message }}</p>
                            @enderror

                            <button
                                type="submit"
                                class="mt-3 rounded-none bg-primary px-5 py-2 font-semibold text-primary-foreground"
                            >
                                Enviar valoración
                            </button>
                        </form>
                    @elseif ($order->rating)
                        <p class="mt-2 text-sm font-semibold text-foreground">
                            Ya valoraste este pedido:
                            {{ $order->rating->rating }} de 5 estrellas.
                        </p>
                    @endif
                    </div>
                </div>
            @empty
                <p class="mt-5 text-foreground/75">
                    Todavía no tienes pedidos vinculados a tu cuenta.
                </p>
            @endforelse

            <div class="mt-6">
                {{ $orders->fragment('account-orders')->links('pagination.storefront', ['label' => 'Paginación de mis pedidos']) }}
            </div>
        </section>

