<section class="promo-banners" data-promo-carousel aria-label="Promociones destacadas" aria-roledescription="carrusel">
    <h1 class="sr-only">Chifa Mi Hogar · Chifa a domicilio</h1>
    <div class="promo-banners__track" data-promo-track tabindex="0" aria-label="Desliza para ver promociones">
        @foreach($banners as $banner)
            @php
                $desktopImage = app(\App\Services\CatalogImageService::class)->url($banner, 'detail');
                $mobileAsset = $banner->mobileMediaAsset;
                $mobileImage = $mobileAsset && \Illuminate\Support\Facades\Storage::disk('public')->exists($mobileAsset->detail_path)
                    ? $mobileAsset->detailUrl() : null;
            @endphp
            <a class="promo-banners__slide" href="{{ $banner->destinationUrl() }}" data-promo-slide aria-label="{{ $banner->title }}: ver detalles">
                <picture>
                    @if($mobileImage)
                        <source media="(max-width: 639px)" srcset="{{ $mobileImage }}" width="{{ $mobileAsset->width }}" height="{{ $mobileAsset->height }}">
                    @endif
                    <img src="{{ $desktopImage }}" alt="{{ $banner->title }}" width="{{ $banner->mediaAsset->width }}" height="{{ $banner->mediaAsset->height }}"
                         loading="{{ $loop->first ? 'eager' : 'lazy' }}" fetchpriority="{{ $loop->first ? 'high' : 'auto' }}" decoding="async">
                </picture>
            </a>
        @endforeach
    </div>
    @if($banners->count() > 1)
        <div class="promo-banners__controls" data-promo-controls hidden>
            <button type="button" data-promo-prev aria-label="Promoción anterior">←</button>
            <div class="promo-banners__dots">
                @foreach($banners as $banner)
                    <button type="button" data-promo-index="{{ $loop->index }}" aria-label="Ver promoción {{ $loop->iteration }}: {{ $banner->title }}" @if($loop->first) aria-current="true" @endif><span></span></button>
                @endforeach
            </div>
            <button type="button" data-promo-next aria-label="Promoción siguiente">→</button>
            <span class="sr-only" data-promo-status role="status" aria-live="polite"></span>
        </div>
    @endif
</section>
