<?php

return [
    'enabled' => (bool) env('ADMIN_PUSH_ENABLED', false),
    'credentials' => env('FIREBASE_CREDENTIALS', '/etc/chifa-mi-hogar/firebase-admin.json'),
];
