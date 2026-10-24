<?php

return [
    'access_token' => env('META_WHATSAPP_ACCESS_TOKEN'),

    'phone_number_id' => env('META_WHATSAPP_PHONE_NUMBER_ID'),

    'verify_token' => env('META_WHATSAPP_VERIFY_TOKEN'),

    'app_secret' => env('META_WHATSAPP_APP_SECRET'),

    'api_version' => env('META_WHATSAPP_API_VERSION', 'v23.0'),
];