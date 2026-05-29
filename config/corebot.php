<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Support requests (public form on the welcome page)
    |--------------------------------------------------------------------------
    */

    'support_request_email' => env('SUPPORT_REQUEST_EMAIL'),

    /*
    |--------------------------------------------------------------------------
    | MaxMind GeoLite2 database (GeoIP2 City .mmdb)
    |--------------------------------------------------------------------------
    */

    'geoip_database_path' => env('GEOIP_DATABASE_PATH'),

    /*
    |--------------------------------------------------------------------------
    | Public demo page (/demo)
    |--------------------------------------------------------------------------
    */

    'demo_bot_public_key' => env('DEMO_BOT_PUBLIC_KEY'),

    /*
    |--------------------------------------------------------------------------
    | DOCX text extraction (python-docx via scripts/extract_docx.py)
    |--------------------------------------------------------------------------
    |
    | Absolute path to a Python 3 interpreter (e.g. project venv). When null,
    | Laravel invokes `python3` from the worker process PATH.
    |
    */

    'docx_python' => env('DOCX_PYTHON'),

];
