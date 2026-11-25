<?php

namespace App\Events\Realtime;

class OrderDeleted extends OrderBroadcast
{
    public function broadcastAs(): string
    {
        return 'order.deleted';
    }
}
