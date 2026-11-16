<?php

namespace App\Repositories\Eloquent;

use App\DTOs\ProductFilterDTO;
use App\Repositories\Contracts\CatalogRepositoryInterface;
use App\Support\Performance\Flags;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class CatalogRepository implements CatalogRepositoryInterface
{
    private const COLUMNS = ['id', 'name', 'description', 'price', 'cost', 'category', 'image', 'created_at', 'updated_at'];

    private const MIN_FULLTEXT_LENGTH = 3;

    public function __construct(private readonly Flags $flags) {}

    public function page(ProductFilterDTO $filter, int $page, string $connection): array
    {
        try {
            return $this->run($filter, $page, $connection, $this->useFullText($filter, $connection));
        } catch (QueryException $e) {
            if (!$this->useFullText($filter, $connection)) {
                throw $e;
            }

            return $this->run($filter, $page, $connection, false);
        }
    }

    public function find(int $id, string $connection): ?array
    {
        $row = DB::connection($connection)->table('products')->select(self::COLUMNS)->where('id', $id)->first();

        return $row === null ? null : (array) $row;
    }

    private function run(ProductFilterDTO $filter, int $page, string $connection, bool $fullText): array
    {
        $query = $this->filtered($connection, $filter, $fullText);

        $total = (clone $query)->count();

        $rows = $query
            ->select(self::COLUMNS)
            ->orderBy('id')
            ->forPage($page, $filter->perPage)
            ->get()
            ->map(fn ($row) => (array) $row)
            ->all();

        return ['rows' => $rows, 'total' => $total];
    }

    private function filtered(string $connection, ProductFilterDTO $filter, bool $fullText): Builder
    {
        $query = DB::connection($connection)->table('products');

        if ($filter->search !== null && $filter->search !== '') {
            $fullText ? $this->matchAgainst($query, $filter->search) : $this->like($query, $filter->search);
        }

        if ($filter->category !== null && $filter->category !== '') {
            $query->where('category', $filter->category);
        }

        return $query;
    }

    private function like(Builder $query, string $term): void
    {
        $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);

        $query->where(function ($inner) use ($escaped) {
            $inner->where('name', 'like', "%{$escaped}%")
                ->orWhere('description', 'like', "%{$escaped}%");
        });
    }

    private function matchAgainst(Builder $query, string $term): void
    {
        $query->whereRaw('MATCH(name, description) AGAINST (? IN BOOLEAN MODE)', [$this->booleanQuery($term)]);
    }

    private function booleanQuery(string $term): string
    {
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $term, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode(' ', array_map(fn (string $token) => "+{$token}*", $tokens));
    }

    private function useFullText(ProductFilterDTO $filter, string $connection): bool
    {
        return $filter->search !== null
            && mb_strlen($filter->search) >= self::MIN_FULLTEXT_LENGTH
            && $this->flags->enabled('fulltext')
            && DB::connection($connection)->getDriverName() === 'mysql';
    }
}
