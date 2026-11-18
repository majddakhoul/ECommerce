<?php

return [
    'connection' => [
        'host' => env('RABBITMQ_HOST', '127.0.0.1'),
        'port' => (int) env('RABBITMQ_PORT', 5672),
        'user' => env('RABBITMQ_USER', 'guest'),
        'password' => env('RABBITMQ_PASSWORD', 'guest'),
        'vhost' => env('RABBITMQ_VHOST', '/'),
    ],

    'queues' => [
        'emails' => env('QUEUE_EMAILS', 'emails'),
        'broadcasts' => env('QUEUE_BROADCASTS', 'broadcasts'),
    ],

    'jobs' => [
        'failed_exchange' => env('RABBITMQ_FAILED_EXCHANGE', 'ec.jobs.failed'),
        'failed_routing_key' => env('RABBITMQ_FAILED_ROUTING_KEY', 'application.%s.failed'),
    ],

    'stock' => [
        'exchange' => env('RABBITMQ_EVENTS_EXCHANGE', 'ec.events'),
        'routing_key' => env('RABBITMQ_STOCK_ROUTING_KEY', 'stock.changed'),
        'queue' => env('RABBITMQ_STOCK_QUEUE', 'ec.stock.changed'),
        'dead_letter_exchange' => env('RABBITMQ_STOCK_DLX', 'ec.events.dlx'),
        'dead_letter_routing_key' => env('RABBITMQ_STOCK_DEAD_KEY', 'stock.changed.dead'),
        'dead_letter_queue' => env('RABBITMQ_STOCK_DLQ', 'ec.stock.changed.dlq'),
        'prefetch' => (int) env('RABBITMQ_STOCK_PREFETCH', 20),
        'max_attempts' => (int) env('RABBITMQ_STOCK_MAX_ATTEMPTS', 3),
        'dedupe_ttl_seconds' => (int) env('RABBITMQ_STOCK_DEDUPE_TTL', 3600),
    ],
];
