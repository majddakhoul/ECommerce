<?php

namespace App\Support\Performance;

use Closure;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class CatalogCache
{
    private const VERSION_KEY = 'catalog:version';

    public function __construct(private readonly Flags $flags) {}

    public function version(): int
    {
        $current = $this->store()->get(self::VERSION_KEY);

        if ($current === null) {
            $this->store()->add(self::VERSION_KEY, 1);

            return 1;
        }

        return (int) $current;
    }

    public function invalidate(): void
    {
        if (!$this->flags->enabled('invalidation')) {
            return;
        }

        $this->bump();
    }

    public function flush(): void
    {
        $this->bump();
    }

    private function bump(): void
    {
        $this->version();
        $this->store()->increment(self::VERSION_KEY);
    }

    public function remember(string $name, array $params, Closure $resolver): array
    {
        if (!$this->flags->enabled('cache')) {
            return [$resolver(), false];
        }

        $key = sprintf('catalog:v%d:%s:%s', $this->version(), $name, md5(json_encode($params)));
        $cached = $this->store()->get($key);

        if ($cached !== null) {
            return [$cached, true];
        }

        $value = $resolver();

        $this->store()->put($key, $value, $this->ttl());

        return [$value, false];
    }

    private function ttl(): int
    {
        $base = (int) config('performance.ttl_seconds');

        return $base + random_int(0, max(1, intdiv($base, 10)));
    }

    private function store(): Repository
    {
        return Cache::store(config('performance.store'));
    }
}
