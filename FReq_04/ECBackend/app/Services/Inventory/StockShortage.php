<?php

namespace App\Services\Inventory;

class StockShortage
{
    public const INSUFFICIENT_STOCK = 'INSUFFICIENT_STOCK';

    public const PRODUCT_NOT_FOUND = 'PRODUCT_NOT_FOUND';

    public function __construct(
        public readonly int $productId,
        public readonly int $requested,
        public readonly int $available,
        public readonly string $reason
    ) {}
}
