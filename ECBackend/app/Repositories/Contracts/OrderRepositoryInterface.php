<?php

namespace App\Repositories\Contracts;

use App\DTOs\OrderFilterDTO;
use App\Models\Order;
use Illuminate\Pagination\LengthAwarePaginator;

interface OrderRepositoryInterface
{
    public function paginate(OrderFilterDTO $filter): LengthAwarePaginator;

    public function create(array $data, array $items): Order;

    public function update(Order $order, array $data): Order;

    public function delete(Order $order): bool;
}
