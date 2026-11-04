<?php

return [
    'enabled' => (bool) env('FACEBOOK_LOGIN_ENABLED', false),
    'app_id' => env('FACEBOOK_LOGIN_APP_ID'),
    'app_secret' => env('FACEBOOK_LOGIN_APP_SECRET'),
    'redirect_uri' => env('FACEBOOK_LOGIN_REDIRECT_URI'),
    // Select a supported version from the Meta application dashboard.
    'graph_version' => env('FACEBOOK_LOGIN_GRAPH_VERSION'),
];
