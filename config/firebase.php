<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Server credentials (service account)
    |--------------------------------------------------------------------------
    | Used by kreait/firebase-php to verify ID tokens server-side. Point this at
    | the service account JSON, kept OUTSIDE the web root and out of version
    | control. On Hostinger, place it above public_html.
    */
    'credentials' => env('FIREBASE_CREDENTIALS', storage_path('firebase/service-account.json')),

    /*
    |--------------------------------------------------------------------------
    | Web SDK config
    |--------------------------------------------------------------------------
    | Public Firebase web config, injected into the login page so the browser
    | can perform sign-in. These values are not secret.
    */
    'web' => [
        'api_key'     => env('FIREBASE_API_KEY'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN'),
        'project_id'  => env('FIREBASE_PROJECT_ID'),
        'app_id'      => env('FIREBASE_APP_ID'),
    ],
];
