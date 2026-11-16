<?php

namespace App\Support\Performance;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class Flags
{
    public const NAMES = ['cache', 'etag', 'replica', 'fulltext', 'invalidation'];

    public function enabled(string $name): bool
    {
        $this->assertKnown($name);

        $stored = $this->store()->get($this->key($name));

        return $stored === null ? (bool) config("performance.defaults.{$name}") : (bool) $stored;
    }

    public function set(string $name, bool $value): void
    {
        $this->assertKnown($name);

        $this->store()->forever($this->key($name), $value);
    }

    public function all(): array
    {
        $flags = [];

        foreach (self::NAMES as $name) {
            $flags[$name] = $this->enabled($name);
        }

        return $flags;
    }

    public function reset(): void
    {
        foreach (self::NAMES as $name) {
            $this->store()->forget($this->key($name));
        }
    }

    private function store(): Repository
    {
        return Cache::store(config('performance.store'));
    }

    private function key(string $name): string
    {
        return "perf:flag:{$name}";
    }

    private function assertKnown(string $name): void
    {
        if (!in_array($name, self::NAMES, true)) {
            throw new InvalidArgumentException("Unknown performance flag [{$name}].");
        }
    }
}
