<?php

namespace App\Repositories\Contracts;

interface CartRepositoryInterface
{
    public function get(int $userId): array;

    public function put(int $userId, array $items): void;

    public function forget(int $userId): void;
}
