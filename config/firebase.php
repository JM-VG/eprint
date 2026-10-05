<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Realtime Database Driver
    |--------------------------------------------------------------------------
    |
    | "auto"  -> the in-memory fake while running tests, the real REST client
    |            everywhere else.
    | "array" -> always use the in-memory fake (local development without a
    |            service account, or tests).
    | "firebase" -> always use the live Realtime Database REST client.
    |
    */

    'driver' => env('FIREBASE_DRIVER', 'auto'),

    /*
    |--------------------------------------------------------------------------
    | Realtime Database Connection
    |--------------------------------------------------------------------------
    */

    'project_id' => env('FIREBASE_PROJECT_ID', 'e-print-e1185'),

    'database_url' => env('FIREBASE_DATABASE_URL', 'https://e-print-e1185-default-rtdb.firebaseio.com'),

    /*
    |--------------------------------------------------------------------------
    | Service Account Credentials
    |--------------------------------------------------------------------------
    |
    | Either the path to the service account JSON file, or the raw JSON itself
    | via FIREBASE_CREDENTIALS_JSON (useful on hosts where uploading a file is
    | awkward). Never commit the real file: storage/app/firebase/*.json is
    | gitignored.
    |
    */

    'credentials' => env('FIREBASE_CREDENTIALS_JSON') ?: env('FIREBASE_CREDENTIALS', storage_path('app/firebase/service-account.json')),

    /*
    |--------------------------------------------------------------------------
    | Token Cache & Transactions
    |--------------------------------------------------------------------------
    |
    | OAuth2 access tokens from the service account are valid for one hour.
    | They are cached with the file store so the app does not depend on the
    | (removed) database cache.
    |
    */

    'cache_tokens' => (bool) env('FIREBASE_CACHE_TOKENS', true),

    'transaction_retries' => (int) env('FIREBASE_TRANSACTION_RETRIES', 5),

    /*
    |--------------------------------------------------------------------------
    | Public Web Config
    |--------------------------------------------------------------------------
    |
    | These values are shipped to the browser and are not secrets. They are
    | used by the Firebase JS SDK to subscribe to Realtime Database updates
    | on the client.
    |
    */

    'web' => [
        'api_key' => env('FIREBASE_API_KEY', 'AIzaSyBK7_M0Ip-Ogsx2QlOHaiOZXcxPvKmOk1s'),
        'auth_domain' => env('FIREBASE_AUTH_DOMAIN', 'e-print-e1185.firebaseapp.com'),
        'database_url' => env('FIREBASE_DATABASE_URL', 'https://e-print-e1185-default-rtdb.firebaseio.com'),
        'project_id' => env('FIREBASE_PROJECT_ID', 'e-print-e1185'),
        'storage_bucket' => env('FIREBASE_STORAGE_BUCKET', 'e-print-e1185.firebasestorage.app'),
        'messaging_sender_id' => env('FIREBASE_MESSAGING_SENDER_ID', '990026970317'),
        'app_id' => env('FIREBASE_APP_ID', '1:990026970317:web:19d60fb445f0929cd02584'),
        'measurement_id' => env('FIREBASE_MEASUREMENT_ID', 'G-R3TSD962VK'),
    ],

];
