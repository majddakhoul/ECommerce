<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class StockChanged implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public string $eventId,
        public int $productId,
        public int $stock,
        public bool $deleted,
        public string $reason,
        public string $occurredAt,
        public int $version = 0
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('stock')];
    }

    public function broadcastAs(): string
    {
        return 'stock.changed';
    }

    public function broadcastWith(): array
    {
        return [
            'event_id' => $this->eventId,
            'product_id' => $this->productId,
            'stock' => $this->stock,
            'deleted' => $this->deleted,
            'reason' => $this->reason,
            'version' => $this->version,
            'occurred_at' => $this->occurredAt,
        ];
    }
}
