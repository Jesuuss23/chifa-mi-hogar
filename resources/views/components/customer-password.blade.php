@props([
    'id' => 'password', 'name' => 'password', 'label' => 'Contraseña',
    'autocomplete' => 'new-password', 'minLength' => null, 'maxLength' => null,
    'strength' => false, 'placeholder' => '', 'errorKey' => null,
])
@php($fieldError = $errorKey ?? $name)
<div class="customer-password" data-password-field>
    <label for="{{ $id }}" class="block text-sm font-medium">{{ $label }}</label>
    <div class="customer-password__control">
        <input id="{{ $id }}" name="{{ $name }}" type="password" required
               autocomplete="{{ $autocomplete }}" placeholder="{{ $placeholder }}"
               @if($minLength) minlength="{{ $minLength }}" @endif
               @if($maxLength) maxlength="{{ $maxLength }}" @endif
               @if($strength) data-password-strength @endif
               aria-invalid="{{ $errors->has($fieldError) ? 'true' : 'false' }}"
               aria-describedby="{{ $id }}-guidance{{ $errors->has($fieldError) ? ' '.$id.'-error' : '' }}">
        <button type="button" data-password-toggle hidden aria-controls="{{ $id }}"
                aria-label="Mostrar {{ mb_strtolower($label) }}" aria-pressed="false">Ver</button>
    </div>
    <span id="{{ $id }}-guidance" class="sr-only">{{ $minLength ? 'Mínimo '.$minLength.' caracteres.' : 'Introduce tu contraseña.' }}{{ $strength ? ' El indicador es una estimación local, no una garantía de seguridad.' : '' }}</span>
    @if($strength)
        <div class="customer-password__feedback" data-password-feedback hidden>
            <meter min="0" max="4" value="0" data-password-meter aria-label="Fortaleza estimada de la contraseña"></meter>
            <span data-password-label role="status" aria-live="polite"></span>
        </div>
    @endif
    @error($fieldError)<p id="{{ $id }}-error" role="alert" class="mt-1 text-sm text-destructive">{{ $message }}</p>@enderror
</div>
