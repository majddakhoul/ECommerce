<?php

return [
    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'inventory' => [
        'host' => env('INVENTORY_GRPC_HOST', '127.0.0.1'),
        'port' => (int) env('INVENTORY_GRPC_PORT', 50051),
        'api_key' => env('INVENTORY_API_KEY', ''),
        'timeout' => (float) env('INVENTORY_GRPC_TIMEOUT', 2),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],
];
