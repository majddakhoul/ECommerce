<?php

namespace App\Repositories\Eloquent;

use App\DTOs\ProductFilterDTO;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

class ProductRepository implements ProductRepositoryInterface
{
    public function paginate(ProductFilterDTO $filter): LengthAwarePaginator
    {
        return Product::query()
            ->when($filter->search, function ($query, $term) {
                $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('description', 'like', "%{$term}%");
                });
            })
            ->when($filter->category, fn ($query, $category) => $query->where('category', $category))
            ->when($filter->inStock !== null, fn ($query) => $filter->inStock
                ? $query->where('stock', '>', 0)
                : $query->where('stock', 0))
            ->orderBy('id')
            ->paginate($filter->perPage)
            ->withQueryString();
    }

    public function categories(): Collection
    {
        return Product::whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');
    }

    public function create(array $data): Product
    {
        return Product::create($data);
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product;
    }

    public function delete(Product $product): bool
    {
        return $product->delete();
    }
}
