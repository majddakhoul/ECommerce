<?php

return [
    'rate_limit' => (int) env('API_RATE_LIMIT', 120),
    'default_per_page' => 15,
    'max_per_page' => 100,
    'login_max_attempts' => (int) env('LOGIN_MAX_ATTEMPTS', 5),
    'login_lockout_minutes' => (int) env('LOGIN_LOCKOUT_MINUTES', 15),
    'cart_lock_seconds' => (int) env('CART_LOCK_SECONDS', 30),
    'cart_lock_wait_seconds' => (int) env('CART_LOCK_WAIT_SECONDS', 5),
];
