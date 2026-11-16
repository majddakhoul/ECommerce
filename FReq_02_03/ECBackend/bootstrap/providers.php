<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\InventoryServiceProvider;
use App\Providers\RepositoryServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    RepositoryServiceProvider::class,
    InventoryServiceProvider::class,
];
