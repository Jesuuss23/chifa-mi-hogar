<div id="combos" class="scroll-mt-28">
@if($combos->isNotEmpty())
<section class="catalog-surface combo-carousel border-b border-border bg-accent/40"
         data-combo-carousel aria-labelledby="combos-title">
    <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:py-10">
        <div class="mb-6 flex flex-wrap items-end justify-between gap-5">
            <div class="max-w-xl">
                
                <h2 id="combos-title" class="text-3xl font-black sm:text-4xl">Combos y promociones</h2>
                
            </div>
            <div class="combo-carousel__controls items-center gap-2" data-combo-controls hidden>
                <button type="button" data-combo-prev aria-label="Ver combos anteriores"
                        aria-controls="combo-cards"
                        class="flex h-11 w-11 items-center justify-center border border-border bg-card text-xl text-primary disabled:cursor-default disabled:opacity-40">
                    <span aria-hidden="true">←</span>
                </button>
                <button type="button" data-combo-next aria-label="Ver siguientes combos"
                        aria-controls="combo-cards"
                        class="flex h-11 w-11 items-center justify-center border border-primary bg-primary text-xl text-primary-foreground disabled:cursor-default disabled:opacity-40">
                    <span aria-hidden="true">→</span>
                </button>
            </div>
        </div>

        <p id="combo-scroll-help" class="sr-only">
            Desliza para explorar. También puedes recorrer los combos con el teclado.
        </p>

        <ul id="combo-cards" data-combo-viewport class="combo-carousel__track"
            tabindex="0" aria-label="Combos disponibles" aria-describedby="combo-scroll-help">
            @php
                $firstOfferId = $combos->first(fn ($entry) => $entry->reference_price > (float) $entry->price)?->id;
            @endphp
            @foreach($combos as $combo)
                @php
                    $includedItems = $combo->items->filter(fn ($item) => $item->product || $item->extra);
                @endphp
                <li class="combo-carousel__card">
                    <article class="promo-card flex h-full flex-col overflow-hidden border border-border bg-card">
                        <x-combo-offer :combo="$combo" :animate="$combo->id === $firstOfferId" />
                        <div class="relative aspect-[16/10] overflow-hidden bg-muted">
                            @if($combo->mediaAsset || $combo->image_path)
                                <x-catalog-image :item="$combo" variant="card"
                                    :loading="$loop->first ? 'eager' : 'lazy'"
                                    class="h-full w-full object-cover" />
                            @else
                                <div class="flex h-full flex-col items-center justify-center gap-2 text-primary">
                                    <span class="text-4xl font-black" aria-hidden="true">CM</span>
                                    <span class="text-xs font-bold uppercase tracking-widest">Tu próximo favorito</span>
                                </div>
                            @endif
                            <span class="sr-only">
                                Combo
                            </span>
                        </div>
                        <div class="flex flex-1 flex-col p-5">
                            <h3 class="text-xl font-black leading-tight">
                                <a href="{{ route('combos.show', $combo) }}" class="hover:text-primary">{{ $combo->name }}</a>
                            </h3>
                            @if($combo->description)
                                <p class="mt-2 line-clamp-2 text-sm leading-6 text-foreground/75">{{ $combo->description }}</p>
                            @endif
                            @if($includedItems->isNotEmpty())
                                <ul class="mt-4 space-y-1 text-sm text-foreground/75" aria-label="Incluye">
                                    @foreach($includedItems->take(3) as $item)
                                        <li>{{ $item->quantity }} × {{ $item->product?->name ?? $item->extra?->name }}</li>
                                    @endforeach
                                </ul>
                                @if($includedItems->count() > 3)
                                    <p class="mt-1 text-xs text-foreground/75">+ {{ $includedItems->count() - 3 }} más</p>
                                @endif
                            @endif
                            <div class="mt-auto pt-5">
                                <div class="mb-4 flex items-baseline justify-between gap-3 border-t border-border pt-4">
                                    
                                    <x-combo-price :combo="$combo" />
                                </div>
                                <a href="{{ route('combos.show', $combo) }}"
                                   aria-label="Ver combo: {{ $combo->name }}"
                                   class="flex min-h-12 items-center justify-center bg-primary px-4 py-3 text-sm font-bold text-primary-foreground transition hover:bg-primary/90">
                                    Ver combo <span class="ml-2" aria-hidden="true">↗</span>
                                </a>
                            </div>
                        </div>
                    </article>
                </li>
            @endforeach
        </ul>
        
    </div>
</section>
@endif
</div>
