<?php

namespace App\Repositories\Contracts;

use App\DTOs\ProductFilterDTO;

interface CatalogRepositoryInterface
{
    public function page(ProductFilterDTO $filter, int $page, string $connection): array;

    public function find(int $id, string $connection): ?array;
}
