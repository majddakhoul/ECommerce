<?php

namespace App\Observers;

use App\Models\Product;
use App\Support\Performance\CatalogCache;
use App\Support\Performance\ReplicaGuard;

class ProductObserver
{
    public function __construct(
        private readonly CatalogCache $cache,
        private readonly ReplicaGuard $replica
    ) {}

    public function saved(Product $product): void
    {
        $this->touch();
    }

    public function deleted(Product $product): void
    {
        $this->touch();
    }

    private function touch(): void
    {
        $this->replica->markWrite();
        $this->cache->invalidate();
    }
}
