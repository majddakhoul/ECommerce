<?php

namespace Tests\Support;

use App\Exceptions\InventoryUnavailableException;
use App\Services\Inventory\InventoryGatewayInterface;
use App\Services\Inventory\ReservationResult;
use App\Services\Inventory\StockShortage;

class FakeInventoryGateway implements InventoryGatewayInterface
{
    private array $stocks = [];

    private array $reservations = [];

    public bool $unavailable = false;

    public array $calls = [];

    public function set(int $productId, int $stock): void
    {
        $this->stocks[$productId] = $stock;
    }

    public function stock(int $productId): int
    {
        return $this->stocks[$productId] ?? 0;
    }

    public function has(int $productId): bool
    {
        return array_key_exists($productId, $this->stocks);
    }

    public function reservationStatus(string $reservationId): ?string
    {
        return $this->reservations[$reservationId]['status'] ?? null;
    }

    public function getStock(int $productId): int
    {
        $this->touch('getStock');

        return $this->stock($productId);
    }

    public function getStocks(array $productIds): array
    {
        $this->touch('getStocks');

        $levels = [];

        foreach ($productIds as $productId) {
            if ($this->has((int) $productId)) {
                $levels[(int) $productId] = $this->stocks[(int) $productId];
            }
        }

        return $levels;
    }

    public function availableProductIds(): array
    {
        $this->touch('availableProductIds');

        return array_keys(array_filter($this->stocks, fn (int $stock) => $stock > 0));
    }

    public function setStock(int $productId, int $stock): void
    {
        $this->touch('setStock');

        $this->stocks[$productId] = $stock;
    }

    public function deleteStock(int $productId): void
    {
        $this->touch('deleteStock');

        unset($this->stocks[$productId]);
    }

    public function reserve(string $reservationId, array $items): ReservationResult
    {
        $this->touch('reserve');

        if (isset($this->reservations[$reservationId])) {
            return new ReservationResult($this->reservations[$reservationId]['status'] === 'reserved', $reservationId);
        }

        $shortages = [];

        foreach ($items as $productId => $quantity) {
            if (!$this->has((int) $productId)) {
                $shortages[] = new StockShortage((int) $productId, (int) $quantity, 0, StockShortage::PRODUCT_NOT_FOUND);
            } elseif ($this->stocks[(int) $productId] < $quantity) {
                $shortages[] = new StockShortage((int) $productId, (int) $quantity, $this->stocks[(int) $productId], StockShortage::INSUFFICIENT_STOCK);
            }
        }

        if ($shortages !== []) {
            return new ReservationResult(false, $reservationId, $shortages);
        }

        foreach ($items as $productId => $quantity) {
            $this->stocks[(int) $productId] -= (int) $quantity;
        }

        $this->reservations[$reservationId] = ['items' => $items, 'status' => 'reserved'];

        return new ReservationResult(true, $reservationId);
    }

    public function release(string $reservationId): void
    {
        $this->touch('release');

        $reservation = $this->reservations[$reservationId] ?? null;

        if ($reservation === null || $reservation['status'] !== 'reserved') {
            return;
        }

        foreach ($reservation['items'] as $productId => $quantity) {
            $this->stocks[(int) $productId] = ($this->stocks[(int) $productId] ?? 0) + (int) $quantity;
        }

        $this->reservations[$reservationId]['status'] = 'released';
    }

    private function touch(string $method): void
    {
        if ($this->unavailable) {
            throw new InventoryUnavailableException;
        }

        $this->calls[] = $method;
    }
}
