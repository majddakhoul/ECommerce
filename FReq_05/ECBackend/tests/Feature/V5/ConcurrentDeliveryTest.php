<?php

namespace Tests\Feature\V5;

use App\Events\Realtime\StockChanged;
use App\Jobs\SendOrderMail;
use App\Mail\OrderPlacedUserMail;
use App\Services\NotificationService;
use App\Services\Realtime\StockEventHandler;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\ApiTestCase;

class ConcurrentDeliveryTest extends ApiTestCase
{
    public function test_a_mail_job_that_finds_the_lock_taken_sends_nothing_until_it_is_free(): void
    {
        Mail::fake();

        $product = $this->makeProduct(['stock' => 5]);
        $member = $this->member();

        $order = $this->actingAsApi($member)
            ->postJson('/api/v4/sync/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertCreated()
            ->json('data');

        Mail::fake();

        $job = new SendOrderMail($order['id'], NotificationService::PLACED_USER, $member->email);
        $key = 'mail-sent:' . sha1(implode('|', [$order['id'], NotificationService::PLACED_USER, $member->email, null]));

        $holder = Cache::lock($key . ':lock', 30);
        $this->assertTrue($holder->get());

        $job->handle($this->app->make(NotificationService::class));
        Mail::assertNothingSent();

        $holder->release();

        $job->handle($this->app->make(NotificationService::class));
        $job->handle($this->app->make(NotificationService::class));

        Mail::assertSent(OrderPlacedUserMail::class, 1);
    }

    public function test_a_stock_event_that_finds_the_lock_taken_is_skipped(): void
    {
        Event::fake([StockChanged::class]);

        $body = json_encode([
            'event_id' => 'evt-lock',
            'type' => 'stock.changed',
            'product_id' => 4,
            'stock' => 9,
            'deleted' => false,
            'reason' => 'reserved',
            'version' => 1,
            'occurred_at' => '2026-11-20T10:00:00Z',
        ]);

        $holder = Cache::lock('stock-event:evt-lock:lock', 30);
        $this->assertTrue($holder->get());

        $handler = $this->app->make(StockEventHandler::class);

        $this->assertFalse($handler->handle($body));
        Event::assertNotDispatched(StockChanged::class);

        $holder->release();

        $this->assertTrue($handler->handle($body));
        Event::assertDispatchedTimes(StockChanged::class, 1);
    }
}
