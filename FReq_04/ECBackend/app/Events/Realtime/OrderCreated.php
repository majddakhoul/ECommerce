<?php

namespace App\Events\Realtime;

class OrderCreated extends OrderBroadcast
{
    public function broadcastAs(): string
    {
        return 'order.created';
    }
}
