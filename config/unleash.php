<?php

use Unleash\Client\Metrics\DefaultMetricsHandler;
use Unleash\Client\Variant\DefaultVariantHandler;

return [
    'app_url' => env('UNLEASH_URL'),
    'api_key' => env('UNLEASH_API_KEY'),
    'instance_id' => env('UNLEASH_INSTANCE_ID'),
    'app_name' => env('UNLEASH_APP_NAME', env('APP_NAME')),

    'cache' => [
        'driver' => env('UNLEASH_CACHE_DRIVER', env('CACHE_DRIVER')),
        'ttl' => env('UNLEASH_CACHE_TTL', 15),

        'stale_driver' => env('UNLEASH_STALE_CACHE_DRIVER'),
        'stale_ttl' => env('UNLEASH_STALE_CACHE_TTL', 30 * 60),
    ],

    'strategies' => [],

    'variant_handler' => DefaultVariantHandler::class,

    'events' => env('UNLEASH_EVENTS_ENABLED', false),

    'metrics' => [
        'enabled' => env('UNLEASH_METRICS_ENABLED', true),
        'interval' => env('UNLEASH_METRICS_INTERVAL', 60_000),
        'handler' => DefaultMetricsHandler::class,
    ],

    'development' => env('UNLEASH_DEVELOPMENT', false),

    'bootstrap_file' => env('UNLEASH_BOOTSTRAP_FILE'),
];
