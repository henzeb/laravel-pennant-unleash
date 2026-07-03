<?php

return [
    'app_url' => env('UNLEASH_URL'),
    'api_key' => env('UNLEASH_API_KEY'),
    'instance_id' => env('UNLEASH_INSTANCE_ID'),

    'app_name' => env('UNLEASH_APP_NAME', env('APP_NAME')),

    'cache_driver' => env('UNLEASH_CACHE_DRIVER', env('CACHE_DRIVER')),

    'development' => env('UNLEASH_DEVELOPMENT', false),

    'bootstrap_file' => env('UNLEASH_BOOTSTRAP_FILE'),
];
