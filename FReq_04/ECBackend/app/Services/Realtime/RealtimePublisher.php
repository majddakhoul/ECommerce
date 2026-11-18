<?php

namespace App\Services\Realtime;

use App\Events\Realtime\CartUpdated;
use App\Events\Realtime\OrderCancelled;
use App\Events\Realtime\OrderCreated;
use App\Events\Realtime\OrderDeleted;
use App\Events\Realtime\OrderStatusChanged;
use App\Events\Realtime\ProductChanged;
use App\Http\Resources\OrderResource;
use App\Http\Resources\ProductResource;
use App\Models\Order;
use App\Models\Product;
use App\Support\Processing\ProcessingMode;

class RealtimePublisher
{
    public function __construct(private readonly ProcessingMode $processing) {}

    public function productCreated(Product $product): void
    {
        $this->emit(fn () => new ProductChanged(ProductChanged::CREATED, $product->id, $this->product($product)));
    }

    public function productUpdated(Product $product): void
    {
        $this->emit(fn () => new ProductChanged(ProductChanged::UPDATED, $product->id, $this->product($product)));
    }

    public function productDeleted(int $productId): void
    {
        $this->emit(fn () => new ProductChanged(ProductChanged::DELETED, $productId));
    }

    public function orderCreated(Order $order): void
    {
        $this->emit(fn () => new OrderCreated($order->id, $order->user_id, $this->order($order)));
    }

    public function orderStatusChanged(Order $order, string $previousStatus): void
    {
        $this->emit(fn () => new OrderStatusChanged($order->id, $order->user_id, $this->order($order), $previousStatus));
    }

    public function orderCancelled(Order $order, string $previousStatus): void
    {
        $this->emit(fn () => new OrderCancelled($order->id, $order->user_id, $this->order($order), $previousStatus));
    }

    public function orderDeleted(Order $order): void
    {
        $this->emit(fn () => new OrderDeleted($order->id, $order->user_id, $this->order($order)));
    }

    public function cartUpdated(int $userId, array $cart): void
    {
        $this->emit(fn () => new CartUpdated($userId, $cart));
    }

    public function emptyCart(int $userId): void
    {
        $this->cartUpdated($userId, ['items' => [], 'total_quantity' => 0, 'total_price' => '0.00']);
    }

    private function emit(callable $factory): void
    {
        if (!$this->processing->isAsync()) {
            return;
        }

        event($factory());
    }

    private function product(Product $product): array
    {
        return (new ProductResource($product))->resolve();
    }

    private function order(Order $order): array
    {
        $order->loadMissing(['items', 'user:id,name,email']);

        return json_decode(json_encode(new OrderResource($order)), true);
    }
}
