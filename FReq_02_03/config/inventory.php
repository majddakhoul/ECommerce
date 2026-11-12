<?php
return [
    'enabled' => (bool) env('INVENTORY_ENABLED', true),
    'address' => env('INVENTORY_GRPC_ADDRESS', 'inventory:50051'),
    'timeout_ms' => (int) env('INVENTORY_TIMEOUT_MS', 1500),
];
