<?php
return [
    'product_cache_enabled' => (bool) env('PRODUCT_CACHE_ENABLED', true),
    'product_cache_ttl' => (int) env('PRODUCT_CACHE_TTL', 300),
    'etag_enabled' => (bool) env('ETAG_ENABLED', true),
    'read_connection' => env('DB_READ_CONNECTION', env('DB_CONNECTION', 'mysql')),
];
