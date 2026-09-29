<?php

return [

    /*
    |--------------------------------------------------------------------------
    | DOCX Conversion
    |--------------------------------------------------------------------------
    |
    | When enabled, uploaded DOCX files are converted to PDF with LibreOffice
    | so their page count can be read. Otherwise staff enter it on Accept.
    |
    */

    'docx_conversion' => (bool) env('EPRINT_DOCX_CONVERSION', false),

    'soffice_path' => env('EPRINT_SOFFICE_PATH', 'soffice'),

    /*
    |--------------------------------------------------------------------------
    | Initial Admin Account
    |--------------------------------------------------------------------------
    |
    | Used by the AdminSeeder to create the super admin on first seed.
    | An existing admin is never overwritten by re-seeding.
    |
    */

    'admin' => [
        'username' => env('ADMIN_USERNAME', 'admin'),
        'password' => env('ADMIN_PASSWORD'),
    ],

];
