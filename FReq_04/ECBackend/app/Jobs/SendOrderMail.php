<?php

namespace App\Jobs;

use App\Services\NotificationService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendOrderMail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    public int $timeout = 60;

    public function __construct(
        public int $orderId,
        public string $kind,
        public string $recipient,
        public ?string $previousStatus = null
    ) {
        $this->onQueue((string) config('messaging.queues.emails'));
        $this->afterCommit();
    }

    public function backoff(): array
    {
        return [10, 60, 300, 900];
    }

    public function handle(NotificationService $notifications): void
    {
        $key = $this->idempotencyKey();

        if (Cache::has($key)) {
            return;
        }

        $notifications->deliverQueued($this->orderId, $this->kind, $this->recipient, $this->previousStatus);

        Cache::put($key, true, now()->addDay());
    }

    public function failed(Throwable $exception): void
    {
        Log::critical('Order email permanently failed', [
            'order_id' => $this->orderId,
            'kind' => $this->kind,
            'recipient' => $this->recipient,
            'attempts' => $this->attempts(),
            'error' => $exception->getMessage(),
        ]);
    }

    private function idempotencyKey(): string
    {
        return 'mail-sent:' . sha1(implode('|', [$this->orderId, $this->kind, $this->recipient, $this->previousStatus]));
    }
}
