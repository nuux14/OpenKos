<?php

return [

    'name' => env('APP_NAME', 'MariaCloud Property Management'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost'),

    'build' => [
        'version' => env('OPENKOS_BUILD_VERSION'),
        'channel' => env('OPENKOS_BUILD_CHANNEL'),
        'commit_sha' => env('OPENKOS_BUILD_COMMIT_SHA'),
    ],

    'timezone' => 'UTC',

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'supported_locales' => [
        'en' => 'English',
        'so' => 'Somali',
        'am' => 'Amharic',
    ],

    'locale_aliases' => [
        'en' => 'en',
        'en-us' => 'en',
        'en-gb' => 'en',
        'so' => 'so',
        'so-et' => 'so',
        'am' => 'am',
        'am-et' => 'am',
    ],

    'intl_locales' => [
        'en' => 'en-ET',
        'so' => 'so-ET',
        'am' => 'am-ET',
    ],

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', '')),
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];