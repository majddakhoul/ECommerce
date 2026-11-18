<?php

namespace App\Services\Realtime;

use App\Events\Realtime\StockChanged;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

class StockEventHandler
{
    public function handle(string $body): bool
    {
        $payload = $this->decode($body);
        $key = 'stock-event:' . $payload['event_id'];

        if (Cache::has($key)) {
            return false;
        }

        event(new StockChanged(
            $payload['event_id'],
            $payload['product_id'],
            $payload['stock'],
            $payload['deleted'],
            $payload['reason'],
            $payload['occurred_at'],
            $payload['version']
        ));

        Cache::put($key, true, now()->addSeconds((int) config('messaging.stock.dedupe_ttl_seconds')));

        return true;
    }

    private function decode(string $body): array
    {
        $data = json_decode($body, true);

        if (!is_array($data)) {
            throw new InvalidArgumentException('The stock event is not valid JSON.');
        }

        $eventId = $data['event_id'] ?? null;
        $productId = $data['product_id'] ?? null;
        $stock = $data['stock'] ?? null;

        if (!is_string($eventId) || $eventId === '') {
            throw new InvalidArgumentException('The stock event has no event_id.');
        }

        if (!is_int($productId) || $productId < 1) {
            throw new InvalidArgumentException('The stock event has an invalid product_id.');
        }

        if (!is_int($stock) || $stock < 0) {
            throw new InvalidArgumentException('The stock event has an invalid stock value.');
        }

        return [
            'event_id' => $eventId,
            'product_id' => $productId,
            'stock' => $stock,
            'deleted' => (bool) ($data['deleted'] ?? false),
            'reason' => (string) ($data['reason'] ?? 'unknown'),
            'occurred_at' => (string) ($data['occurred_at'] ?? now()->toIso8601String()),
            'version' => is_int($data['version'] ?? null) ? $data['version'] : 0,
        ];
    }
}
