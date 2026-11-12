<?php
namespace App\Inventory;
class NullInventoryClient implements InventoryClientInterface
{
    public function reserve(string $reservationId, array $items): void {}
    public function release(string $reservationId): void {}
    public function ensureProduct(int $productId, string $name, int $stock): void {}
    public function setStock(int $productId, int $stock): void {}
}
