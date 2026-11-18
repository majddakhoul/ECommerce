<?php

namespace App\Events\Realtime;

trait BroadcastsRealtime
{
    public int $tries = 3;

    public array $backoff = [2, 10];

    public bool $afterCommit = true;

    public function broadcastQueue(): string
    {
        return (string) config('messaging.queues.broadcasts');
    }
}
