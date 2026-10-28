<?php

namespace App\Repositories\Contracts;

use App\DTOs\ProductFilterDTO;
use App\Models\Product;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

interface ProductRepositoryInterface
{
    public function paginate(ProductFilterDTO $filter): LengthAwarePaginator;

    public function categories(): Collection;

    public function create(array $data): Product;

    public function update(Product $product, array $data): Product;

    public function delete(Product $product): bool;

    public function findMany(array $ids): Collection;

    public function decrementStock(int $id, int $quantity): bool;

    public function incrementStock(int $id, int $quantity): void;
}
