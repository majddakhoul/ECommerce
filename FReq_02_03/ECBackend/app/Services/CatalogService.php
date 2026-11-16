<?php

namespace App\Services;

use App\DTOs\ProductFilterDTO;
use App\Models\Product;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Services\Inventory\InventoryGatewayInterface;
use App\Support\Performance\CatalogCache;
use App\Support\Performance\ReplicaGuard;
use Illuminate\Support\Collection;

class CatalogService
{
    public function __construct(
        private readonly CatalogRepositoryInterface $catalog,
        private readonly InventoryGatewayInterface $inventory,
        private readonly CatalogCache $cache,
        private readonly ReplicaGuard $replica
    ) {}

    public function list(ProductFilterDTO $filter, int $page): array
    {
        $connection = $this->replica->connection();

        if ($filter->inStock !== null) {
            $payload = $this->catalog->page($filter, $page, $connection);
            $payload = $this->applyStockFilter($payload, $filter);

            return $this->result($payload, 'BYPASS', $connection);
        }

        [$payload, $hit] = $this->cache->remember(
            'list',
            [$filter->search, $filter->category, $filter->perPage, $page],
            fn () => $this->catalog->page($filter, $page, $connection)
        );

        return $this->result($payload, $hit ? 'HIT' : 'MISS', $hit ? 'cache' : $connection);
    }

    public function find(int $id): ?array
    {
        $connection = $this->replica->connection();

        [$row, $hit] = $this->cache->remember(
            'product',
            [$id],
            fn () => $this->catalog->find($id, $connection)
        );

        if ($row === null) {
            return null;
        }

        $product = $this->hydrate([$row])->first();
        $product->stock = $this->inventory->getStock($id);

        return ['product' => $product, 'cache' => $hit ? 'HIT' : 'MISS', 'source' => $hit ? 'cache' : $connection];
    }

    private function result(array $payload, string $cache, string $source): array
    {
        $products = $this->hydrate($payload['rows']);
        $stocks = $this->inventory->getStocks($products->pluck('id')->all());

        foreach ($products as $product) {
            $product->stock = $stocks[$product->id] ?? 0;
        }

        return ['products' => $products, 'total' => $payload['total'], 'cache' => $cache, 'source' => $source];
    }

    private function applyStockFilter(array $payload, ProductFilterDTO $filter): array
    {
        $available = array_flip($this->inventory->availableProductIds());

        $rows = array_values(array_filter(
            $payload['rows'],
            fn (array $row) => $filter->inStock === isset($available[$row['id']])
        ));

        return ['rows' => $rows, 'total' => count($rows)];
    }

    private function hydrate(array $rows): Collection
    {
        return collect($rows)->map(fn (array $row) => (new Product)->newFromBuilder($row));
    }
}
