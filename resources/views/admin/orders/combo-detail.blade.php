@if($item->item_type === 'combo')
    <div class="mt-3 border border-border bg-muted p-4" data-combo-detail="{{ $item->id }}">
        <h3 class="text-sm font-semibold">Componentes seleccionados por combo</h3>
        <p class="mt-1 text-xs text-foreground/75">
            Esta línea contiene {{ $item->quantity }} {{ $item->quantity === 1 ? 'combo' : 'combos' }} con la misma selección.
            Los adicionales indicados ya están incluidos en el importe de la línea.
        </p>
        <ul class="mt-3 space-y-3 text-sm">
            @forelse($item->comboComponents as $component)
                <li class="border-t border-border pt-2">
                    <p class="font-semibold">{{ $component->component_name }}</p>
                    <p class="text-xs text-foreground/75">
                        {{ $component->component_type === 'product' ? 'Plato' : 'Adición' }}
                    </p>
                    <dl class="mt-1 grid grid-cols-2 gap-x-3 gap-y-1">
                        <dt>Por combo</dt><dd>{{ $component->selected_quantity }}</dd>
                        <dt>Incluidos por combo</dt><dd>{{ $component->included_quantity }}</dd>
                        <dt>Adicionales por combo</dt><dd>{{ $component->additional_quantity }}</dd>
                        <dt>Total a preparar en esta línea</dt>
                        <dd class="font-semibold">{{ $component->selected_quantity * $item->quantity }}</dd>
                    </dl>
                    @if($component->additional_quantity > 0)
                        <p class="mt-1 text-xs text-foreground/75">
                            Cargo adicional por combo: S/ {{ number_format((float) $component->additional_total, 2) }}.
                        </p>
                    @endif
                </li>
            @empty
                <li>No hay componentes guardados para este combo en el pedido.</li>
            @endforelse
        </ul>
    </div>
@endif
