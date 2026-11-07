@props(['input', 'maxMb' => 5])
<div data-admin-image-preview data-input-id="{{ $input }}" data-max-bytes="{{ $maxMb * 1024 * 1024 }}" class="admin-image-preview">
    <div data-preview-panel class="admin-image-preview__panel" hidden>
        <p class="font-semibold text-sm">Nueva imagen · Sin guardar</p>
        <div data-preview-image class="admin-image-preview__image"></div>
        <p data-preview-name class="admin-image-preview__name"></p>
        <p data-preview-meta class="text-xs text-foreground/75"></p>
        <p class="text-xs text-foreground/75">La imagen actual se conserva hasta que guardes el formulario.</p>
        <button type="button" data-preview-clear class="admin-image-preview__clear">Quitar selección</button>
    </div>
    <p data-preview-status role="status" aria-live="polite" class="admin-image-preview__status"></p>
</div>
