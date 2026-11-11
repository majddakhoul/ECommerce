<?php

use App\Providers\AppServiceProvider;
use App\Inventory\InventoryServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    InventoryServiceProvider::class,
    AuthServiceProvider::class,
    RepositoryServiceProvider::class,
];
