<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PerfIndexesCommand extends Command
{
    protected $signature = 'perf:indexes {action : add, drop or status}';

    protected $description = 'Add or drop the performance indexes used for the before and after measurements';

    private const INDEXES = [
        'products' => [
            'ft_products_name_description' => ['fulltext', ['name', 'description']],
            'idx_products_category_id' => ['index', ['category', 'id']],
        ],
        'orders' => [
            'idx_orders_user_id_id' => ['index', ['user_id', 'id']],
            'idx_orders_status_id' => ['index', ['status', 'id']],
        ],
    ];

    private const FOREIGN_KEY_FALLBACK = [
        'orders.idx_orders_user_id_id' => ['orders_user_id_foreign', ['user_id']],
    ];

    public function handle(): int
    {
        if (!in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            $this->error('This command supports MySQL only.');

            return self::FAILURE;
        }

        $action = $this->argument('action');

        if (!in_array($action, ['add', 'drop', 'status'], true)) {
            $this->error('The action must be add, drop or status.');

            return self::FAILURE;
        }

        foreach (self::INDEXES as $table => $indexes) {
            foreach ($indexes as $name => [$type, $columns]) {
                $this->apply($action, $table, $name, $type, $columns);
            }
        }

        return self::SUCCESS;
    }

    private function apply(string $action, string $table, string $name, string $type, array $columns): void
    {
        $exists = Schema::hasIndex($table, $name);

        if ($action === 'status') {
            $this->line(sprintf('%-45s %s', "{$table}.{$name}", $exists ? 'present' : 'missing'));

            return;
        }

        if ($action === 'add' && !$exists) {
            Schema::table($table, fn (Blueprint $blueprint) => $type === 'fulltext'
                ? $blueprint->fullText($columns, $name)
                : $blueprint->index($columns, $name));
            $this->info("added {$table}.{$name}");

            return;
        }

        if ($action === 'drop' && $exists) {
            $this->restoreForeignKeyIndex($table, $name);

            Schema::table($table, fn (Blueprint $blueprint) => $type === 'fulltext'
                ? $blueprint->dropFullText($name)
                : $blueprint->dropIndex($name));
            $this->info("dropped {$table}.{$name}");

            return;
        }

        $this->line("skipped {$table}.{$name}");
    }

    private function restoreForeignKeyIndex(string $table, string $name): void
    {
        $fallback = self::FOREIGN_KEY_FALLBACK["{$table}.{$name}"] ?? null;

        if ($fallback === null || Schema::hasIndex($table, $fallback[0])) {
            return;
        }

        [$fallbackName, $columns] = $fallback;

        Schema::table($table, fn (Blueprint $blueprint) => $blueprint->index($columns, $fallbackName));
        $this->info("restored {$table}.{$fallbackName}");
    }
}
