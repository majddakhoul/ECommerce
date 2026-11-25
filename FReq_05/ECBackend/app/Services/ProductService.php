<?php

namespace App\Services;

use App\DTOs\CreateProductDTO;
use App\DTOs\ProductFilterDTO;
use App\DTOs\UpdateProductDTO;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Services\Inventory\InventoryGatewayInterface;
use App\Services\Realtime\RealtimePublisher;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ProductService
{
    public function __construct(
        protected ProductRepositoryInterface $productRepo,
        protected InventoryGatewayInterface $inventory,
        protected RealtimePublisher $realtime
    ) {}

    public function list(ProductFilterDTO $filter): LengthAwarePaginator
    {
        $availableIds = $filter->inStock === null ? null : $this->inventory->availableProductIds();

        $products = $this->productRepo->paginate($filter, $availableIds);

        $this->attachStock($products->getCollection());

        return $products;
    }

    public function show(Product $product): Product
    {
        $product->stock = $this->inventory->getStock($product->id);

        return $product;
    }

    public function categories(): Collection
    {
        return $this->productRepo->categories();
    }

    public function create(CreateProductDTO $dto): Product
    {
        $attributes = $dto->attributes();
        $image = $dto->image ? $dto->image->store('products', 'public') : null;

        if ($image) {
            $attributes['image'] = $image;
        }

        try {
            $product = DB::transaction(function () use ($attributes, $dto) {
                $product = $this->productRepo->create($attributes);

                $this->inventory->setStock($product->id, $dto->stock);

                return $product;
            });
        } catch (Throwable $e) {
            if ($image) {
                Storage::disk('public')->delete($image);
            }

            throw $e;
        }

        $product->stock = $dto->stock;

        $this->realtime->productCreated($product);

        return $product;
    }

    public function update(Product $product, UpdateProductDTO $dto): Product
    {
        $attributes = $dto->attributes;
        $stock = array_key_exists('stock', $attributes) ? (int) $attributes['stock'] : null;
        unset($attributes['stock']);

        $previousImage = $product->image;
        $newImage = $dto->image ? $dto->image->store('products', 'public') : null;

        if ($newImage) {
            $attributes['image'] = $newImage;
        }

        try {
            $updated = DB::transaction(function () use ($product, $attributes, $stock) {
                $updated = $this->productRepo->update($product, $attributes);

                if ($stock !== null) {
                    $this->inventory->setStock($product->id, $stock);
                }

                return $updated;
            });
        } catch (Throwable $e) {
            if ($newImage) {
                Storage::disk('public')->delete($newImage);
            }

            throw $e;
        }

        if ($newImage && $previousImage) {
            Storage::disk('public')->delete($previousImage);
        }

        $updated->stock = $stock ?? $this->inventory->getStock($updated->id);

        $this->realtime->productUpdated($updated);

        return $updated;
    }

    public function delete(Product $product): void
    {
        $image = $product->image;

        DB::transaction(function () use ($product) {
            $this->productRepo->delete($product);

            $this->inventory->deleteStock($product->id);
        });

        if ($image) {
            Storage::disk('public')->delete($image);
        }

        $this->realtime->productDeleted($product->id);
    }

    private function attachStock(Collection $products): void
    {
        $stocks = $this->inventory->getStocks($products->pluck('id')->all());

        foreach ($products as $product) {
            $product->stock = $stocks[$product->id] ?? 0;
        }
    }
}
