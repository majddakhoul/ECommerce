<?php

namespace Tests\Feature\V4;

use App\Events\Realtime\OrderCancelled;
use App\Events\Realtime\OrderCreated;
use App\Events\Realtime\OrderStatusChanged;
use App\Jobs\SendOrderMail;
use App\Mail\OrderPlacedAdminMail;
use App\Mail\OrderPlacedUserMail;
use App\Mail\OrderStatusUpdatedMail;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\ApiTestCase;

class OrderProcessingTest extends ApiTestCase
{
    public function test_a_sync_order_sends_mail_inline_and_publishes_nothing(): void
    {
        Mail::fake();
        Queue::fake();
        Event::fake([OrderCreated::class]);

        $product = $this->makeProduct(['stock' => 5]);

        $this->actingAsApi($this->member())
            ->postJson('/api/v4/sync/orders', ['items' => [['product_id' => $product->id, 'quantity' => 2]]])
            ->assertCreated();

        Mail::assertSent(OrderPlacedUserMail::class, 1);
        Mail::assertSent(OrderPlacedAdminMail::class, 1);
        Queue::assertNothingPushed();
        Event::assertNotDispatched(OrderCreated::class);
        $this->assertSame(3, $this->stockOf($product));
    }

    public function test_an_async_order_queues_the_mail_and_publishes_the_event(): void
    {
        Mail::fake();
        Queue::fake();
        Event::fake([OrderCreated::class]);

        $product = $this->makeProduct(['stock' => 5]);

        $this->actingAsApi($this->member())
            ->postJson('/api/v4/async/orders', ['items' => [['product_id' => $product->id, 'quantity' => 2]]])
            ->assertCreated()
            ->assertHeader('X-Processing-Mode', 'async');

        Mail::assertNothingSent();
        Queue::assertPushed(SendOrderMail::class, 2);
        Queue::assertPushedOn(config('messaging.queues.emails'), SendOrderMail::class);
        Event::assertDispatched(OrderCreated::class, fn (OrderCreated $event) => $event->order['items'][0]['quantity'] === 2);
        $this->assertSame(3, $this->stockOf($product));
    }

    public function test_the_queued_mail_job_delivers_once_even_when_it_runs_twice(): void
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
        $job->handle($this->app->make(NotificationService::class));
        $job->handle($this->app->make(NotificationService::class));

        Mail::assertSent(OrderPlacedUserMail::class, 1);
    }

    public function test_an_async_status_change_queues_the_mail_and_publishes_the_event(): void
    {
        Mail::fake();
        Queue::fake();
        Event::fake([OrderStatusChanged::class]);

        $product = $this->makeProduct(['stock' => 5]);

        $order = $this->actingAsApi($this->member())
            ->postJson('/api/v4/sync/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->json('data');

        $this->actingAsApi($this->admin())
            ->putJson("/api/v4/async/orders/{$order['id']}/status", ['status' => 'confirmed'])
            ->assertOk();

        Queue::assertPushed(SendOrderMail::class, fn (SendOrderMail $job) => $job->kind === NotificationService::STATUS_UPDATED && $job->previousStatus === 'pending');
        Event::assertDispatched(OrderStatusChanged::class, fn (OrderStatusChanged $event) => $event->order['status'] === 'confirmed' && $event->previousStatus === 'pending');
    }

    public function test_a_sync_status_change_sends_mail_inline(): void
    {
        Mail::fake();
        Queue::fake();

        $product = $this->makeProduct(['stock' => 5]);

        $order = $this->actingAsApi($this->member())
            ->postJson('/api/v4/sync/orders', ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->json('data');

        Mail::fake();

        $this->actingAsApi($this->admin())
            ->putJson("/api/v4/sync/orders/{$order['id']}/status", ['status' => 'confirmed'])
            ->assertOk();

        Mail::assertSent(OrderStatusUpdatedMail::class, 1);
        Queue::assertNothingPushed();
    }

    public function test_an_async_cancellation_publishes_the_cancelled_event_and_restocks(): void
    {
        Queue::fake();
        Event::fake([OrderCancelled::class]);

        $product = $this->makeProduct(['stock' => 5]);
        $member = $this->member();

        $order = $this->actingAsApi($member)
            ->postJson('/api/v4/sync/orders', ['items' => [['product_id' => $product->id, 'quantity' => 2]]])
            ->json('data');

        $this->actingAsApi($member)
            ->postJson("/api/v4/async/orders/{$order['id']}/cancel")
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        Event::assertDispatched(OrderCancelled::class, fn (OrderCancelled $event) => $event->userId === $member->id);
        $this->assertSame(5, $this->stockOf($product));
    }

    public function test_an_order_event_targets_the_owner_and_the_admin_channels(): void
    {
        $event = new OrderCreated(7, 42, ['id' => 7]);

        $names = array_map(fn ($channel) => $channel->name, $event->broadcastOn());

        $this->assertSame(['private-orders.user.42', 'private-orders.admin'], $names);
        $this->assertSame('order.created', $event->broadcastAs());
    }
}
