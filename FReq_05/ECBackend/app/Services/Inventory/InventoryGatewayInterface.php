<?php

namespace App\Services\Inventory;

interface InventoryGatewayInterface
{
    public function getStock(int $productId): int;

    public function getStocks(array $productIds): array;

    public function availableProductIds(): array;

    public function setStock(int $productId, int $stock): void;

    public function deleteStock(int $productId): void;

    public function reserve(string $reservationId, array $items): ReservationResult;

    public function release(string $reservationId): void;
}
