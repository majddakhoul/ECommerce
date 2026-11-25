<?php

namespace App\Console\Commands;

use App\Support\OrderStatus;
use App\Support\Performance\CatalogCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class PerfSeedCommand extends Command
{
    protected $signature = 'perf:seed
        {--products=50000 : number of products}
        {--orders=200000 : number of orders}
        {--users=200 : number of users to own the orders}
        {--max-items=4 : maximum order lines per order}
        {--stock=50 : stock level written to the inventory service database}
        {--chunk=1000 : rows per insert}
        {--skip-inventory : do not write stock to the inventory database}';

    protected $description = 'Seed large volumes of products, users, orders and order items with batched inserts';

    private const WORDS = [
        'phone', 'laptop', 'mouse', 'keyboard', 'monitor', 'camera', 'speaker', 'router', 'tablet', 'charger',
        'jacket', 'shirt', 'shoes', 'watch', 'bottle', 'lamp', 'chair', 'table', 'book', 'novel',
        'cable', 'drive', 'memory', 'sensor', 'printer', 'scanner', 'headset', 'cooker', 'kettle', 'blender',
    ];

    private const ADJECTIVES = [
        'compact', 'wireless', 'premium', 'classic', 'portable', 'smart', 'durable', 'lightweight', 'ergonomic', 'modern',
    ];

    private const CATEGORIES = [
        'electronics', 'clothing', 'books', 'home', 'sports', 'toys', 'garden', 'beauty', 'office', 'kitchen',
        'audio', 'gaming', 'outdoor', 'health', 'tools', 'travel', 'pets', 'music', 'art', 'food',
    ];

    public function handle(CatalogCache $cache): int
    {
        mt_srand(20260101);
        DB::connection()->disableQueryLog();

        $chunk = max(1, (int) $this->option('chunk'));
        $now = now()->toDateTimeString();

        $userIds = $this->seedUsers((int) $this->option('users'), $now);
        $productRange = $this->seedProducts((int) $this->option('products'), $chunk, $now);
        $this->seedOrders((int) $this->option('orders'), $userIds, $productRange, (int) $this->option('max-items'), $chunk);

        if (!$this->option('skip-inventory')) {
            $this->seedInventory($productRange, (int) $this->option('stock'), $chunk);
        }

        $cache->flush();

        $this->info('Seeding finished.');

        return self::SUCCESS;
    }

    private function seedUsers(int $count, string $now): array
    {
        $hash = Hash::make('password');
        $rows = [];

        for ($i = 1; $i <= $count; $i++) {
            $rows[] = [
                'name' => "Load User {$i}",
                'email' => "load{$i}@example.com",
                'password' => $hash,
                'is_active' => 1,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 500) as $batch) {
            DB::table('users')->insertOrIgnore($batch);
        }

        $ids = DB::table('users')->where('email', 'like', 'load%@example.com')->pluck('id')->all();

        $this->info(sprintf('users ready: %d', count($ids)));

        return $ids;
    }

    private function seedProducts(int $count, int $chunk, string $now): array
    {
        $start = (int) DB::table('products')->max('id') + 1;
        $end = $start + $count - 1;

        $this->info("seeding products {$start}..{$end}");
        $bar = $this->output->createProgressBar($count);

        for ($offset = $start; $offset <= $end; $offset += $chunk) {
            $rows = [];

            for ($id = $offset; $id < min($offset + $chunk, $end + 1); $id++) {
                $first = $this->pick(self::ADJECTIVES);
                $second = $this->pick(self::WORDS);
                $third = $this->pick(self::WORDS);
                $price = mt_rand(500, 150000) / 100;

                $rows[] = [
                    'id' => $id,
                    'name' => ucfirst("{$first} {$second} {$id}"),
                    'description' => "{$first} {$second} with {$third} support, model {$id}, suitable for daily use",
                    'price' => $price,
                    'cost' => round($price * 0.6, 2),
                    'category' => $this->pick(self::CATEGORIES),
                    'image' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            DB::transaction(fn () => DB::table('products')->insert($rows));
            $bar->advance(count($rows));
        }

        $bar->finish();
        $this->newLine();

        return [$start, $end];
    }

    private function seedOrders(int $count, array $userIds, array $productRange, int $maxItems, int $chunk): void
    {
        if ($userIds === [] || $count === 0) {
            return;
        }

        [$firstProduct, $lastProduct] = $productRange;
        $start = (int) DB::table('orders')->max('id') + 1;
        $end = $start + $count - 1;
        $itemId = (int) DB::table('order_items')->max('id') + 1;

        $this->info("seeding orders {$start}..{$end}");
        $bar = $this->output->createProgressBar($count);

        for ($offset = $start; $offset <= $end; $offset += $chunk) {
            $orders = [];
            $items = [];

            for ($orderId = $offset; $orderId < min($offset + $chunk, $end + 1); $orderId++) {
                $createdAt = now()->subSeconds(mt_rand(0, 60 * 60 * 24 * 365))->toDateTimeString();
                $total = 0;

                for ($line = 0, $lines = mt_rand(1, max(1, $maxItems)); $line < $lines; $line++) {
                    $quantity = mt_rand(1, 5);
                    $price = mt_rand(500, 150000) / 100;
                    $total += $quantity * $price;

                    $items[] = [
                        'id' => $itemId++,
                        'order_id' => $orderId,
                        'product_id' => mt_rand($firstProduct, $lastProduct),
                        'product_name' => 'Seeded product',
                        'quantity' => $quantity,
                        'price' => $price,
                        'created_at' => $createdAt,
                        'updated_at' => $createdAt,
                    ];
                }

                $orders[] = [
                    'id' => $orderId,
                    'user_id' => $userIds[array_rand($userIds)],
                    'reservation_id' => null,
                    'total_price' => $total,
                    'status' => OrderStatus::ALL[array_rand(OrderStatus::ALL)],
                    'delivered_at' => null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ];
            }

            DB::transaction(function () use ($orders, $items) {
                DB::table('orders')->insert($orders);

                foreach (array_chunk($items, 1000) as $batch) {
                    DB::table('order_items')->insert($batch);
                }
            });

            $bar->advance(count($orders));
        }

        $bar->finish();
        $this->newLine();
    }

    private function seedInventory(array $productRange, int $stock, int $chunk): void
    {
        [$first, $last] = $productRange;
        $connection = DB::connection('inventory_seed');

        $this->info('seeding inventory stock');

        for ($offset = $first; $offset <= $last; $offset += $chunk) {
            $rows = [];

            for ($id = $offset; $id < min($offset + $chunk, $last + 1); $id++) {
                $rows[] = ['product_id' => $id, 'stock' => $stock];
            }

            $connection->table('inventory_items')->upsert($rows, ['product_id'], ['stock']);
        }
    }

    private function pick(array $values): string
    {
        return $values[mt_rand(0, count($values) - 1)];
    }
}
