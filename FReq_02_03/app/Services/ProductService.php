<?php
namespace App\Services;
use App\DTOs\CreateProductDTO;
use App\DTOs\ProductFilterDTO;
use App\DTOs\UpdateProductDTO;
use App\Inventory\InventoryClientInterface;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
class ProductService
{
    public function __construct(protected ProductRepositoryInterface $productRepo, protected InventoryClientInterface $inventory) {}
    public function list(ProductFilterDTO $filter): LengthAwarePaginator { return $this->productRepo->paginate($filter); }
    public function categories(): Collection { return $this->productRepo->categories(); }
    public function create(CreateProductDTO $dto): Product
    {
        $attributes = $dto->attributes();
        if ($dto->image) $attributes['image'] = $dto->image->store('products', 'public');
        $product = $this->productRepo->create($attributes);
        try { $this->inventory->ensureProduct($product->id, $product->name, $product->stock); } catch (\Throwable $e) { $this->productRepo->delete($product); if ($product->image) Storage::disk('public')->delete($product->image); throw $e; }
        return $product;
    }
    public function update(Product $product, UpdateProductDTO $dto): Product
    {
        $attributes = $dto->attributes;
        if ($dto->image) { $this->deleteImage($product); $attributes['image'] = $dto->image->store('products', 'public'); }
        $updated = $this->productRepo->update($product, $attributes);
        if (array_key_exists('stock', $attributes)) $this->inventory->setStock($updated->id, (int) $updated->stock);
        else $this->inventory->ensureProduct($updated->id, $updated->name, (int) $updated->stock);
        return $updated;
    }
    public function delete(Product $product): void { $this->deleteImage($product); $this->productRepo->delete($product); }
    private function deleteImage(Product $product): void { if ($product->image) Storage::disk('public')->delete($product->image); }
}
