<title>{{ $seo['title'] }}</title>
<meta name="description" content="{{ $seo['description'] }}">
<link rel="canonical" href="{{ $seo['canonical'] }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Chifa Mi Hogar">
<meta property="og:locale" content="es_PE">
<meta property="og:title" content="{{ $seo['title'] }}">
<meta property="og:description" content="{{ $seo['description'] }}">
<meta property="og:url" content="{{ $seo['canonical'] }}">
<meta property="og:image" content="{{ $seo['image'] }}">
<meta property="og:image:type" content="{{ $seo['image_type'] }}">
<meta property="og:image:alt" content="{{ $seo['image_alt'] }}">
@if($seo['image_width'] && $seo['image_height'])
    <meta property="og:image:width" content="{{ $seo['image_width'] }}">
    <meta property="og:image:height" content="{{ $seo['image_height'] }}">
@endif
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{{ $seo['title'] }}">
<meta name="twitter:description" content="{{ $seo['description'] }}">
<meta name="twitter:image" content="{{ $seo['image'] }}">
<meta name="twitter:image:alt" content="{{ $seo['image_alt'] }}">

{{-- JSON_HEX_* impide que texto del catálogo cierre la etiqueta script. --}}
<script type="application/ld+json">{!! json_encode($seo['structured_data'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) !!}</script>
