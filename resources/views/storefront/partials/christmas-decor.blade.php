{{-- Decorative assets are local; the CSS-only pause control also works without JS. --}}
<div class="christmas-greeting">
    <span class="christmas-greeting__message">
        <img src="{{ asset('images/themes/christmas-hat.svg') }}" alt="" width="48" height="42" aria-hidden="true">
        <span>Una Navidad para compartir</span>
    </span>
    @unless($themePreview)
        <label class="christmas-snow-control">
            <input type="checkbox" class="christmas-snow-pause">
            <span>Pausar nieve</span>
        </label>
    @endunless
</div>
<div class="christmas-decor" aria-hidden="true">
    <img class="christmas-cane christmas-cane--left" src="{{ asset('images/themes/christmas-cane.svg') }}" alt="" width="130" height="210">
    <img class="christmas-cane christmas-cane--right" src="{{ asset('images/themes/christmas-cane.svg') }}" alt="" width="130" height="210">
    <div class="christmas-lights"></div>
    <div class="christmas-snow">
        @for($flake = 0; $flake < 24; $flake++)
            <i style="--snow-x: {{ ($flake * 43 + 7) % 100 }}%; --snow-delay: -{{ ($flake * 7) % 19 }}s; --snow-duration: {{ 12 + $flake % 9 }}s; --snow-size: {{ 3 + $flake % 4 }}px"></i>
        @endfor
    </div>
</div>
