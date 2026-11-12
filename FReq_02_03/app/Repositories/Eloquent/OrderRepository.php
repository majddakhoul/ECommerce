<?php

namespace App\Repositories\Eloquent;

use App\DTOs\OrderFilterDTO;
use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderRepository implements OrderRepositoryInterface
{
    public function paginate(OrderFilterDTO $filter): LengthAwarePaginator
    {
        return Order::with(['items', 'user:id,name,email'])
            ->when($filter->userId, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($filter->status, fn ($query, $status) => $query->where('status', $status))
            ->orderByDesc('id')
            ->paginate($filter->perPage)
            ->withQueryString();
    }

    public function create(array $data, array $items): Order
    {
        $order = Order::create($data);
        $order->items()->createMany($items);

        return $order;
    }

    public function update(Order $order, array $data): Order
    {
        $order->update($data);

        return $order;
    }

    public function delete(Order $order): bool
    {
        return $order->delete();
    }
}
