<?php

namespace App\Events\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;

class CartUpdated implements ShouldBroadcast
{
    use BroadcastsRealtime, Dispatchable;

    public function __construct(public int $userId, public array $cart) {}

    public function broadcastOn(): array
    {
        return [new PrivateChannel("cart.{$this->userId}")];
    }

    public function broadcastAs(): string
    {
        return 'cart.updated';
    }

    public function broadcastWith(): array
    {
        return ['cart' => $this->cart];
    }
}
