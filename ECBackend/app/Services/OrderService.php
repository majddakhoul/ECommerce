<?php

namespace App\Services;

use App\DTOs\OrderFilterDTO;
use App\DTOs\PlaceOrderDTO;
use App\DTOs\UpdateOrderStatusDTO;
use App\Exceptions\ApiException;
use App\Models\Order;
use App\Models\User;
use App\Repositories\Contracts\OrderRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Support\OrderStatus;
use Illuminate\Pagination\LengthAwarePaginator;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo,
        protected ProductRepositoryInterface $productRepo
    ) {}

    public function list(OrderFilterDTO $filter): LengthAwarePaginator
    {
        return $this->orderRepo->paginate($filter);
    }

    public function show(Order $order): Order
    {
        return $order->load(['items', 'user:id,name,email']);
    }

    public function place(User $user, PlaceOrderDTO $dto): Order
    {
        return $this->placeItems($user, $dto->items);
    }

    public function placeItems(User $user, array $items): Order
    {
        $products = $this->productRepo->findMany(array_keys($items));

        foreach ($items as $productId => $quantity) {
            $product = $products->get($productId);

            if (!$product) {
                throw ApiException::notFound("Product {$productId} not found.");
            }

            if ($product->stock < $quantity) {
                throw ApiException::conflict("Insufficient stock for '{$product->name}'.");
            }
        }

        $reserved = [];

        foreach ($items as $productId => $quantity) {
            if (!$this->productRepo->decrementStock($productId, $quantity)) {
                foreach ($reserved as $reservedId => $reservedQuantity) {
                    $this->productRepo->incrementStock($reservedId, $reservedQuantity);
                }

                throw ApiException::conflict("Insufficient stock for '{$products->get($productId)->name}'.");
            }

            $reserved[$productId] = $quantity;
        }

        $lines = [];
        $total = 0;

        foreach ($items as $productId => $quantity) {
            $product = $products->get($productId);
            $total += $product->price * $quantity;

            $lines[] = [
                'product_id' => $productId,
                'product_name' => $product->name,
                'quantity' => $quantity,
                'price' => $product->price,
            ];
        }

        $order = $this->orderRepo->create([
            'user_id' => $user->id,
            'total_price' => $total,
            'status' => OrderStatus::PENDING,
        ], $lines);

        return $order->load('items');
    }

    public function updateStatus(Order $order, UpdateOrderStatusDTO $dto): Order
    {
        if (!OrderStatus::canMove($order->status, $dto->status)) {
            throw ApiException::conflict("Cannot change the order status from '{$order->status}' to '{$dto->status}'.");
        }

        if ($dto->status === OrderStatus::CANCELLED) {
            $this->restock($order);
        }

        $data = ['status' => $dto->status];

        if ($dto->status === OrderStatus::DELIVERED) {
            $data['delivered_at'] = now();
        }

        return $this->orderRepo->update($order, $data)->load('items');
    }

    public function cancel(Order $order): Order
    {
        if (!OrderStatus::isCancellable($order->status)) {
            throw ApiException::conflict("An order with the status '{$order->status}' cannot be cancelled.");
        }

        $this->restock($order);

        return $this->orderRepo->update($order, ['status' => OrderStatus::CANCELLED])->load('items');
    }

    public function delete(Order $order): void
    {
        if (OrderStatus::isCancellable($order->status)) {
            $this->restock($order);
        }

        $this->orderRepo->delete($order);
    }

    private function restock(Order $order): void
    {
        foreach ($order->items as $item) {
            if ($item->product_id) {
                $this->productRepo->incrementStock($item->product_id, $item->quantity);
            }
        }
    }
}
