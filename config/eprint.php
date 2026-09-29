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

];
