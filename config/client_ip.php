<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Client IP detection
    |--------------------------------------------------------------------------
    |
    | In proxy mode, the configured header is checked before Laravel's resolved
    | client IP. Only enable this when requests reach the app through a trusted
    | proxy that controls the header.
    |
    */
    'mode' => env('CLIENT_IP_MODE', 'request'),
    'header' => env('CLIENT_IP_HEADER', 'CF-Connecting-IP'),
    'log' => (bool) env('CLIENT_IP_LOGGING', false),
];
