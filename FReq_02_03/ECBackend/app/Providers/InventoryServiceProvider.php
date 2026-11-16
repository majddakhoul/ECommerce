<?php

namespace App\Providers;

use App\Services\Inventory\GrpcInventoryGateway;
use App\Services\Inventory\InventoryGatewayInterface;
use Illuminate\Support\ServiceProvider;

class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InventoryGatewayInterface::class, fn () => new GrpcInventoryGateway(
            (string) config('services.inventory.host'),
            (int) config('services.inventory.port'),
            (string) config('services.inventory.api_key'),
            (float) config('services.inventory.timeout')
        ));
    }
}
