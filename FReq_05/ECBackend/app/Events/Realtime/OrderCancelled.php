<?php

namespace App\Events\Realtime;

class OrderCancelled extends OrderBroadcast
{
    public function broadcastAs(): string
    {
        return 'order.cancelled';
    }
}
