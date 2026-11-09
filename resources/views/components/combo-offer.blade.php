@props(['combo', 'animate' => false])
@if($combo->reference_price > (float) $combo->price)
    <span class="combo-offer-badge" data-combo-offer>
        <span class="{{ $animate ? 'promo-spark' : '' }}" aria-hidden="true">🔥</span> En oferta
    </span>
    @if($animate)
        <svg class="promo-border" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true" focusable="false">
            <rect x="1" y="1" width="98" height="98" rx="5" pathLength="100" vector-effect="non-scaling-stroke"/>
        </svg>
    @endif
@endif
