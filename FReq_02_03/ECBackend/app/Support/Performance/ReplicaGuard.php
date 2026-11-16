<?php

namespace App\Support\Performance;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

class ReplicaGuard
{
    private const MARKER_KEY = 'perf:last-write';

    public function __construct(private readonly Flags $flags) {}

    public function connection(): string
    {
        $replica = config('performance.replica_connection');

        if ($replica === null || !$this->flags->enabled('replica') || $this->recentlyWritten()) {
            return (string) config('database.default');
        }

        return $replica;
    }

    public function markWrite(): void
    {
        $this->store()->put(self::MARKER_KEY, microtime(true), (int) config('performance.replica_sticky_seconds'));
    }

    public function recentlyWritten(): bool
    {
        return $this->store()->has(self::MARKER_KEY);
    }

    private function store(): Repository
    {
        return Cache::store(config('performance.store'));
    }
}
