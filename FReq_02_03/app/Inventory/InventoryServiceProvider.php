<?php
namespace App\Inventory;
use Illuminate\Support\ServiceProvider;
class InventoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(InventoryClientInterface::class, function ($app) {
            return config('inventory.enabled') ? new GrpcInventoryClient() : new NullInventoryClient();
        });
    }
}
