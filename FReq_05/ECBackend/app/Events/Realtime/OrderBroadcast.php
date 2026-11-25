<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

abstract class OrderBroadcast implements ShouldBroadcast
{
    use BroadcastsRealtime, Dispatchable;

    public function __construct(
        public int $orderId,
        public int $userId,
        public array $order,
        public ?string $previousStatus = null
    ) {}

    abstract public function broadcastAs(): string;

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("orders.user.{$this->userId}"),
            new PrivateChannel('orders.admin'),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'order_id' => $this->orderId,
            'previous_status' => $this->previousStatus,
            'order' => $this->order,
        ];
    }
}
