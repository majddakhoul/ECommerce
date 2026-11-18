<?php

namespace App\Events\Realtime;

class OrderStatusChanged extends OrderBroadcast
{
    public function broadcastAs(): string
    {
        return 'order.status_changed';
    }
}
