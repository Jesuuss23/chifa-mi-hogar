@props(['id', 'name' => 'quantity', 'value' => 1, 'min' => 1, 'max' => 20, 'label' => 'Cantidad'])
@php
    $current = max((int) $min, min((int) $max, (int) $value));
@endphp
<div class="quantity-stepper" data-quantity-stepper role="group" aria-label="{{ $label }}">
    <button type="button" data-quantity-step="-1" aria-label="Disminuir {{ $label }}" @disabled($current <= $min)>−</button>
    <output id="{{ $id }}-output" aria-live="polite">{{ $current }}</output>
    <input id="{{ $id }}" type="hidden" data-quantity-input name="{{ $name }}" value="{{ $current }}" min="{{ $min }}" max="{{ $max }}">
    <button type="button" data-quantity-step="1" aria-label="Aumentar {{ $label }}" @disabled($current >= $max)>+</button>
</div>
