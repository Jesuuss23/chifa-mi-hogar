<?php

return [
    // Activar solo después de configurar las claves del dominio.
    'enabled' => env('TURNSTILE_ENABLED', false),
    'site_key' => env('TURNSTILE_SITE_KEY', ''),
    'secret_key' => env('TURNSTILE_SECRET_KEY', ''),
    'hostnames' => array_values(array_filter(array_map(
        'trim',
        explode(',', env('TURNSTILE_HOSTNAMES', 'chifamihogar.com,www.chifamihogar.com'))
    ))),
];
