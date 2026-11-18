<?php

return [
    'store' => env('PERFORMANCE_STORE', env('CACHE_STORE', 'file')),

    'defaults' => [
        'cache' => (bool) env('PERF_CACHE_ENABLED', true),
        'etag' => (bool) env('PERF_ETAG_ENABLED', true),
        'replica' => (bool) env('PERF_REPLICA_ENABLED', false),
        'fulltext' => (bool) env('PERF_FULLTEXT_ENABLED', true),
        'invalidation' => (bool) env('PERF_INVALIDATION_ENABLED', true),
    ],

    'ttl_seconds' => (int) env('PERF_CACHE_TTL', 120),

    'replica_connection' => env('DB_REPLICA_HOST') ? 'mysql_replica' : null,

    'replica_sticky_seconds' => (int) env('PERF_REPLICA_STICKY_SECONDS', 5),

    'compare' => [
        'default_runs' => 20,
        'max_runs' => 200,
    ],
];
