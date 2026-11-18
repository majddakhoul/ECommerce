<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class ProductChanged implements ShouldBroadcast
{
    use BroadcastsRealtime, Dispatchable;

    public const CREATED = 'created';

    public const UPDATED = 'updated';

    public const DELETED = 'deleted';

    public function __construct(public string $action, public int $productId, public ?array $product = null) {}

    public function broadcastOn(): array
    {
        return [new Channel('products')];
    }

    public function broadcastAs(): string
    {
        return "product.{$this->action}";
    }

    public function broadcastWith(): array
    {
        return [
            'action' => $this->action,
            'product_id' => $this->productId,
            'product' => $this->product,
        ];
    }
}
