<?php

return [
    'enabled' => (bool) env('GOOGLE_LOGIN_ENABLED', false),
    'client_id' => env('GOOGLE_LOGIN_CLIENT_ID'),
    'client_secret' => env('GOOGLE_LOGIN_CLIENT_SECRET'),
    'redirect_uri' => env('GOOGLE_LOGIN_REDIRECT_URI'),
];
