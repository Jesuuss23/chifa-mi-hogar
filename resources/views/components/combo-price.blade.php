@props(['combo'])
@php($reference = $combo->reference_price)
<div class="flex flex-wrap items-baseline gap-2">
    @if($reference > (float) $combo->price)
        <span class="text-sm text-foreground/75">Por separado <del>S/ {{ number_format($reference, 2) }}</del></span>
    @endif
    <span class="whitespace-nowrap text-2xl font-black text-primary"><span class="sr-only">Precio del combo: </span>S/ {{ number_format((float) $combo->price, 2) }}</span>
</div>
