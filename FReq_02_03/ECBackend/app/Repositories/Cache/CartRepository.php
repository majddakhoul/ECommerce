<?php

namespace App\Repositories\Cache;

use App\Repositories\Contracts\CartRepositoryInterface;
use Illuminate\Support\Facades\Cache;

class CartRepository implements CartRepositoryInterface
{
    private const PREFIX = 'cart:user:';

    private const TTL_DAYS = 7;

    public function get(int $userId): array
    {
        return Cache::get($this->key($userId), []);
    }

    public function put(int $userId, array $items): void
    {
        if ($items === []) {
            $this->forget($userId);

            return;
        }

        Cache::put($this->key($userId), $items, now()->addDays(self::TTL_DAYS));
    }

    public function forget(int $userId): void
    {
        Cache::forget($this->key($userId));
    }

    private function key(int $userId): string
    {
        return self::PREFIX . $userId;
    }
}
