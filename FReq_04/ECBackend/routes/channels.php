<?php

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Support\Facades\Broadcast;

$options = ['guards' => [AccessControl::GUARD]];

Broadcast::channel('orders.user.{userId}', function (User $user, $userId) {
    return $user->id === (int) $userId;
}, $options);

Broadcast::channel('orders.admin', function (User $user) {
    return $user->hasRole(AccessControl::ADMIN_ROLE, AccessControl::GUARD);
}, $options);

Broadcast::channel('cart.{userId}', function (User $user, $userId) {
    return $user->id === (int) $userId;
}, $options);
