<?php

namespace App\Support\Concurrency;

use Illuminate\Support\Facades\Cache;

final class OnceGuard
{
    public const EXECUTED = 'executed';

    public const DUPLICATE = 'duplicate';

    public const BUSY = 'busy';

    public function __construct(
        private readonly int $lockSeconds,
        private readonly int $rememberSeconds
    ) {}

    public function run(string $key, callable $work): string
    {
        if (Cache::has($key)) {
            return self::DUPLICATE;
        }

        $lock = Cache::lock($key . ':lock', $this->lockSeconds);

        if (!$lock->get()) {
            return self::BUSY;
        }

        try {
            if (Cache::has($key)) {
                return self::DUPLICATE;
            }

            $work();

            Cache::put($key, true, now()->addSeconds($this->rememberSeconds));

            return self::EXECUTED;
        } finally {
            $lock->release();
        }
    }
}
