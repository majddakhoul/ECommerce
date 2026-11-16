<?php

namespace App\Services\Performance;

use App\DTOs\ProductFilterDTO;
use App\Models\Order;
use App\Services\CatalogService;
use App\Services\ProductService;
use App\Support\Performance\CatalogCache;
use App\Support\Performance\Flags;
use App\Support\Performance\ReplicaGuard;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

class PerformanceLab
{
    public const SCENARIOS = ['list', 'search', 'category', 'detail', 'orders'];

    public const EXPLAIN_SCENARIOS = ['search_like', 'search_fulltext', 'category', 'orders_by_user'];

    public function __construct(
        private readonly Flags $flags,
        private readonly CatalogCache $cache,
        private readonly ReplicaGuard $replica,
        private readonly ProductService $baseline,
        private readonly CatalogService $optimized
    ) {}

    public function status(): array
    {
        return [
            'flags' => $this->flags->all(),
            'cache' => [
                'store' => config('performance.store'),
                'version' => $this->cache->version(),
                'ttl_seconds' => config('performance.ttl_seconds'),
            ],
            'tables' => [
                'products' => DB::table('products')->count(),
                'orders' => DB::table('orders')->count(),
                'order_items' => DB::table('order_items')->count(),
            ],
            'indexes' => [
                'products' => $this->indexNames('products'),
                'orders' => $this->indexNames('orders'),
            ],
            'replica' => $this->replicaStatus(),
        ];
    }

    public function updateFlags(array $changes): array
    {
        foreach ($changes as $name => $value) {
            $this->flags->set($name, (bool) $value);
        }

        return $this->flags->all();
    }

    public function resetFlags(): array
    {
        $this->flags->reset();

        return $this->flags->all();
    }

    public function flushCache(): int
    {
        $this->cache->flush();

        return $this->cache->version();
    }

    public function explain(string $scenario, string $term, ?string $category, int $userId): array
    {
        [$sql, $bindings] = $this->explainQuery($scenario, $term, $category, $userId);

        $prefix = DB::getDriverName() === 'sqlite' ? 'EXPLAIN QUERY PLAN ' : 'EXPLAIN ';

        return [
            'scenario' => $scenario,
            'sql' => $sql,
            'plan' => array_map(fn ($row) => (array) $row, DB::select($prefix . $sql, $bindings)),
        ];
    }

    public function compare(string $scenario, int $runs, string $term, ?string $category): array
    {
        if (!in_array($scenario, self::SCENARIOS, true)) {
            throw new InvalidArgumentException("Unknown scenario [{$scenario}].");
        }

        [$before, $after] = $this->pair($scenario, $term, $category);

        $before['run']();
        $after['run']();

        $baseline = $this->measure($before['run'], $runs);
        $optimized = $this->measure($after['run'], $runs);

        return [
            'scenario' => $scenario,
            'runs' => $runs,
            'flags' => $this->flags->all(),
            'before' => ['label' => $before['label']] + $baseline,
            'after' => ['label' => $after['label']] + $optimized,
            'speedup_p50' => $optimized['p50_ms'] > 0 ? round($baseline['p50_ms'] / $optimized['p50_ms'], 2) : null,
            'queries_saved' => round($baseline['avg_queries'] - $optimized['avg_queries'], 2),
        ];
    }

    private function pair(string $scenario, string $term, ?string $category): array
    {
        $perPage = (int) config('api.default_per_page');

        return match ($scenario) {
            'list' => $this->listPair(new ProductFilterDTO(null, null, null, $perPage), 'GET /products'),
            'search' => $this->listPair(new ProductFilterDTO($term, null, null, $perPage), "GET /products?search={$term}"),
            'category' => $this->listPair(new ProductFilterDTO(null, $category, null, $perPage), "GET /products?category={$category}"),
            'detail' => $this->detailPair(),
            'orders' => $this->ordersPair(),
        };
    }

    private function listPair(ProductFilterDTO $filter, string $name): array
    {
        return [
            [
                'label' => "v2 {$name}",
                'run' => function () use ($filter) {
                    $this->baseline->list($filter);

                    return null;
                },
            ],
            [
                'label' => "v3 {$name}",
                'run' => function () use ($filter) {
                    return $this->optimized->list($filter, 1)['cache'];
                },
            ],
        ];
    }

    private function detailPair(): array
    {
        $id = (int) DB::table('products')->orderBy('id')->value('id');

        return [
            [
                'label' => 'v2 GET /products/{id}',
                'run' => function () use ($id) {
                    $product = \App\Models\Product::find($id);
                    $this->baseline->show($product);

                    return null;
                },
            ],
            [
                'label' => 'v3 GET /products/{id}',
                'run' => fn () => $this->optimized->find($id)['cache'] ?? null,
            ],
        ];
    }

    private function ordersPair(): array
    {
        $limit = (int) config('api.default_per_page');

        return [
            [
                'label' => 'lazy loading (N+1)',
                'run' => function () use ($limit) {
                    foreach (Order::orderByDesc('id')->limit($limit)->get() as $order) {
                        $order->items->count();
                        $order->user?->name;
                    }

                    return null;
                },
            ],
            [
                'label' => 'eager loading',
                'run' => function () use ($limit) {
                    foreach (Order::with(['items', 'user:id,name,email'])->orderByDesc('id')->limit($limit)->get() as $order) {
                        $order->items->count();
                        $order->user?->name;
                    }

                    return null;
                },
            ],
        ];
    }

    private function measure(callable $run, int $runs): array
    {
        $durations = [];
        $queries = [];
        $hits = 0;

        $count = 0;

        DB::listen(function () use (&$count) {
            $count++;
        });

        for ($i = 0; $i < $runs; $i++) {
            $before = $count;

            $startedAt = hrtime(true);
            $outcome = $run();
            $durations[] = (hrtime(true) - $startedAt) / 1e6;
            $queries[] = $count - $before;
            $hits += $outcome === 'HIT' ? 1 : 0;
        }

        sort($durations);

        return [
            'avg_ms' => round(array_sum($durations) / $runs, 3),
            'p50_ms' => round($this->percentile($durations, 50), 3),
            'p95_ms' => round($this->percentile($durations, 95), 3),
            'max_ms' => round(max($durations), 3),
            'avg_queries' => round(array_sum($queries) / $runs, 2),
            'cache_hit_ratio' => round($hits / $runs, 2),
        ];
    }

    private function percentile(array $sorted, int $percent): float
    {
        $index = (int) ceil(($percent / 100) * count($sorted)) - 1;

        return $sorted[max(0, min($index, count($sorted) - 1))];
    }

    private function explainQuery(string $scenario, string $term, ?string $category, int $userId): array
    {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term) . '%';

        return match ($scenario) {
            'search_like' => [
                'SELECT id, name FROM products WHERE (name LIKE ? OR description LIKE ?) ORDER BY id LIMIT 15',
                [$like, $like],
            ],
            'search_fulltext' => [
                'SELECT id, name FROM products WHERE MATCH(name, description) AGAINST (? IN BOOLEAN MODE) ORDER BY id LIMIT 15',
                ["+{$term}*"],
            ],
            'category' => [
                'SELECT id, name FROM products WHERE category = ? ORDER BY id LIMIT 15',
                [$category ?? (string) DB::table('products')->value('category')],
            ],
            'orders_by_user' => [
                'SELECT id, total_price FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 20',
                [$userId],
            ],
            default => throw new InvalidArgumentException("Unknown explain scenario [{$scenario}]."),
        };
    }

    private function indexNames(string $table): array
    {
        try {
            return collect(Schema::getIndexes($table))->pluck('name')->sort()->values()->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function replicaStatus(): array
    {
        $connection = config('performance.replica_connection');

        $status = [
            'configured' => $connection !== null,
            'enabled' => $this->flags->enabled('replica'),
            'reads_from' => $this->replica->connection(),
            'recent_write_pins_primary' => $this->replica->recentlyWritten(),
            'lag_seconds' => null,
            'io_running' => null,
            'sql_running' => null,
        ];

        if ($connection === null) {
            return $status;
        }

        try {
            $row = DB::connection($connection)->selectOne('SHOW REPLICA STATUS');

            if ($row !== null) {
                $row = (array) $row;
                $status['lag_seconds'] = $row['Seconds_Behind_Source'] ?? null;
                $status['io_running'] = $row['Replica_IO_Running'] ?? null;
                $status['sql_running'] = $row['Replica_SQL_Running'] ?? null;
            }
        } catch (Throwable $e) {
            $status['error'] = 'replica unreachable';
        }

        return $status;
    }
}
