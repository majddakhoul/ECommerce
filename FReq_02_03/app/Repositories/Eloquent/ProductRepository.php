<?php
namespace App\Repositories\Eloquent;
use App\DTOs\ProductFilterDTO;
use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
class ProductRepository implements ProductRepositoryInterface
{
    private const VERSION_KEY = 'products:cache-version';
    public function paginate(ProductFilterDTO $filter): LengthAwarePaginator
    {
        $queryKey = 'products:list:'.md5(json_encode([$filter->search, $filter->category, $filter->inStock, $filter->perPage, request()->get('page', 1), $this->version()]));
        return $this->remember($queryKey, fn () => $this->readQuery()
            ->when($filter->search, function ($query, $term) {
                $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
                $query->where(fn ($inner) => $inner->where('name', 'like', "%{$term}%")->orWhere('description', 'like', "%{$term}%"));
            })
            ->when($filter->category, fn ($query, $category) => $query->where('category', $category))
            ->when($filter->inStock !== null, fn ($query) => $filter->inStock ? $query->where('stock', '>', 0) : $query->where('stock', 0))
            ->orderBy('id')->paginate($filter->perPage)->withQueryString());
    }
    public function categories(): Collection
    {
        return $this->remember('products:categories:'.$this->version(), fn () => $this->readQuery()->whereNotNull('category')->distinct()->orderBy('category')->pluck('category'));
    }
    public function create(array $data): Product { $product = Product::create($data); $this->invalidate(); return $product; }
    public function update(Product $product, array $data): Product { $product->update($data); $this->invalidate(); return $product->refresh(); }
    public function delete(Product $product): bool { $result = (bool) $product->delete(); $this->invalidate(); return $result; }
    public function findMany(array $ids): Collection { return Product::query()->whereIn('id', $ids)->get()->keyBy('id'); }
    public function decrementStock(int $id, int $quantity): bool { $result = Product::where('id', $id)->where('stock', '>=', $quantity)->decrement('stock', $quantity) > 0; if ($result) $this->invalidate(); return $result; }
    public function incrementStock(int $id, int $quantity): void { Product::where('id', $id)->increment('stock', $quantity); $this->invalidate(); }
    private function readQuery(): mixed {
        $connection = config('performance.read_connection');
        return $connection && $connection !== config('database.default') ? Product::on($connection) : Product::query();
    }
    private function remember(string $key, callable $callback): mixed { return config('performance.product_cache_enabled') ? Cache::remember($key, now()->addSeconds(config('performance.product_cache_ttl')), $callback) : $callback(); }
    private function version(): int { return (int) Cache::get(self::VERSION_KEY, 1); }
    private function invalidate(): void { if (config('performance.product_cache_enabled')) Cache::forever(self::VERSION_KEY, $this->version() + 1); }
}
