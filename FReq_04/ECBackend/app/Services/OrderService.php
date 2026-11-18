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
use App\Services\Inventory\InventoryGatewayInterface;
use App\Services\Inventory\StockShortage;
use App\Services\Realtime\RealtimePublisher;
use App\Support\OrderStatus;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class OrderService
{
    public function __construct(
        protected OrderRepositoryInterface $orderRepo,
        protected ProductRepositoryInterface $productRepo,
        protected InventoryGatewayInterface $inventory,
        protected NotificationService $notifications,
        protected RealtimePublisher $realtime
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

        foreach (array_keys($items) as $productId) {
            if (!$products->has($productId)) {
                throw ApiException::notFound("Product {$productId} not found.");
            }
        }

        $reservationId = (string) Str::uuid();
        $reservation = $this->inventory->reserve($reservationId, $items);

        if (!$reservation->reserved) {
            throw ApiException::conflict($this->shortageMessage($reservation->shortages, $products));
        }

        try {
            $order = DB::transaction(function () use ($user, $items, $products, $reservationId) {
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

                return $this->orderRepo->create([
                    'user_id' => $user->id,
                    'reservation_id' => $reservationId,
                    'total_price' => $total,
                    'status' => OrderStatus::PENDING,
                ], $lines);
            });
        } catch (Throwable $e) {
            $this->releaseQuietly($reservationId);

            throw $e;
        }

        $order->load('items');

        $this->notifications->sendOrderPlaced($order);
        $this->realtime->orderCreated($order);

        return $order;
    }

    public function updateStatus(Order $order, UpdateOrderStatusDTO $dto): Order
    {
        if (!OrderStatus::canMove($order->status, $dto->status)) {
            throw ApiException::conflict("Cannot change the order status from '{$order->status}' to '{$dto->status}'.");
        }

        $previousStatus = $order->status;

        $data = ['status' => $dto->status];

        if ($dto->status === OrderStatus::DELIVERED) {
            $data['delivered_at'] = now();
        }

        $updated = DB::transaction(function () use ($order, $data, $dto) {
            $updated = $this->orderRepo->update($order, $data);

            if ($dto->status === OrderStatus::CANCELLED) {
                $this->restock($order);
            }

            return $updated;
        })->load('items');

        $this->notifications->sendOrderStatusUpdated($updated, $previousStatus);
        $this->realtime->orderStatusChanged($updated, $previousStatus);

        return $updated;
    }

    public function cancel(Order $order): Order
    {
        if (!OrderStatus::isCancellable($order->status)) {
            throw ApiException::conflict("An order with the status '{$order->status}' cannot be cancelled.");
        }

        $previousStatus = $order->status;

        $updated = DB::transaction(function () use ($order) {
            $updated = $this->orderRepo->update($order, ['status' => OrderStatus::CANCELLED]);

            $this->restock($order);

            return $updated;
        })->load('items');

        $this->realtime->orderCancelled($updated, $previousStatus);

        return $updated;
    }

    public function delete(Order $order): void
    {
        $order->loadMissing(['items', 'user:id,name,email']);

        DB::transaction(function () use ($order) {
            if (OrderStatus::isCancellable($order->status)) {
                $this->restock($order);
            }

            $this->orderRepo->delete($order);

            $this->realtime->orderDeleted($order);
        });
    }

    private function restock(Order $order): void
    {
        if ($order->reservation_id) {
            $this->inventory->release($order->reservation_id);
        }
    }

    private function releaseQuietly(string $reservationId): void
    {
        try {
            $this->inventory->release($reservationId);
        } catch (Throwable $e) {
            Log::critical('Failed to release a stock reservation after the order could not be saved', [
                'reservation_id' => $reservationId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function shortageMessage(array $shortages, $products): string
    {
        if ($shortages === []) {
            return 'The order could not be reserved. Please try again.';
        }

        $messages = [];

        foreach ($shortages as $shortage) {
            $name = $products->get($shortage->productId)?->name ?? "Product {$shortage->productId}";

            $messages[] = $shortage->reason === StockShortage::PRODUCT_NOT_FOUND
                ? "'{$name}' is not available."
                : "Insufficient stock for '{$name}'. Requested: {$shortage->requested}, available: {$shortage->available}.";
        }

        return implode(' ', $messages);
    }
}
