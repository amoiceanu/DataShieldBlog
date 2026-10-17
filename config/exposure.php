<?php

return [
    'enabled' => env('EXPOSURE_ENABLED', true),
    'hmac_key' => env('EXPOSURE_HMAC_KEY'),
    'queue' => 'exposure',
    'display_timezone' => 'Europe/Bucharest',
    'hibp_key' => env('HIBP_API_KEY'),
    'hibp_agent' => env('HIBP_USER_AGENT', 'SecuritateDigitala-ExposureCheck'),
    'hibp_enabled' => env('HIBP_LICENSE_CONFIRMED', false),
    'brave_key' => env('BRAVE_SEARCH_API_KEY'),
    'brave_enabled' => env('BRAVE_STORAGE_RIGHTS_CONFIRMED', false),
];
