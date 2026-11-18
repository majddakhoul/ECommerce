<?php

namespace Tests\Feature\V4;

use App\Events\Realtime\StockChanged;
use App\Services\Realtime\StockEventHandler;
use Illuminate\Support\Facades\Event;
use InvalidArgumentException;
use Tests\TestCase;

class StockEventHandlerTest extends TestCase
{
    private function body(array $overrides = []): string
    {
        return json_encode(array_merge([
            'event_id' => 'evt-1',
            'type' => 'stock.changed',
            'product_id' => 12,
            'stock' => 7,
            'deleted' => false,
            'reason' => 'reserved',
            'version' => 1763287200000000,
            'occurred_at' => '2026-11-16T10:00:00Z',
        ], $overrides));
    }

    public function test_a_valid_event_is_broadcast_once(): void
    {
        Event::fake([StockChanged::class]);

        $handler = $this->app->make(StockEventHandler::class);

        $this->assertTrue($handler->handle($this->body()));
        $this->assertFalse($handler->handle($this->body()));

        Event::assertDispatchedTimes(StockChanged::class, 1);
        Event::assertDispatched(StockChanged::class, fn (StockChanged $e) => $e->productId === 12 && $e->stock === 7 && $e->reason === 'reserved' && $e->broadcastWith()['version'] === 1763287200000000);
    }

    public function test_a_different_event_id_is_broadcast_again(): void
    {
        Event::fake([StockChanged::class]);

        $handler = $this->app->make(StockEventHandler::class);

        $handler->handle($this->body());
        $handler->handle($this->body(['event_id' => 'evt-2', 'stock' => 6]));

        Event::assertDispatchedTimes(StockChanged::class, 2);
    }

    public function test_malformed_events_are_rejected(): void
    {
        $handler = $this->app->make(StockEventHandler::class);

        foreach (['not json', '[]', $this->body(['event_id' => '']), $this->body(['product_id' => 0]), $this->body(['stock' => -1]), $this->body(['stock' => 'many'])] as $body) {
            try {
                $handler->handle($body);
                $this->fail('A malformed stock event was accepted.');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_the_event_uses_the_public_stock_channel(): void
    {
        $event = new StockChanged('evt', 3, 0, false, 'reserved', '2026-11-16T10:00:00Z');

        $this->assertSame('stock', $event->broadcastOn()[0]->name);
        $this->assertSame('stock.changed', $event->broadcastAs());
        $this->assertSame(0, $event->broadcastWith()['stock']);
    }
}
