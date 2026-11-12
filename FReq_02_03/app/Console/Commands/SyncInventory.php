<?php
namespace App\Console\Commands;
use App\Inventory\InventoryClientInterface;
use App\Models\Product;
use Illuminate\Console\Command;
class SyncInventory extends Command
{
    protected $signature = 'inventory:sync';
    protected $description = 'Synchronize application products into the external inventory service';
    public function handle(InventoryClientInterface $inventory): int
    {
        Product::query()->orderBy('id')->each(function (Product $product) use ($inventory) {
            $inventory->ensureProduct($product->id, $product->name, (int) $product->stock);
        });
        $this->info('Inventory synchronized.');
        return self::SUCCESS;
    }
}
