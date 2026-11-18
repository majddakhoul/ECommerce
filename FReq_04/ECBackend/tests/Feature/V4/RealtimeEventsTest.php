<?php

namespace Tests\Feature\V4;

use App\Events\Realtime\CartUpdated;
use App\Events\Realtime\ProductChanged;
use Illuminate\Support\Facades\Event;
use Tests\ApiTestCase;

class RealtimeEventsTest extends ApiTestCase
{
    public function test_an_async_product_lifecycle_publishes_created_updated_and_deleted(): void
    {
        Event::fake([ProductChanged::class]);

        $admin = $this->actingAsApi($this->admin());

        $id = $admin->postJson('/api/v4/async/products', ['name' => 'Live item', 'price' => 12.5, 'cost' => 5, 'stock' => 9])
            ->assertCreated()
            ->json('data.id');

        $admin->putJson("/api/v4/async/products/{$id}", ['stock' => 4])->assertOk();
        $admin->deleteJson("/api/v4/async/products/{$id}")->assertNoContent();

        Event::assertDispatched(ProductChanged::class, fn (ProductChanged $e) => $e->action === 'created' && $e->product['stock'] === 9);
        Event::assertDispatched(ProductChanged::class, fn (ProductChanged $e) => $e->action === 'updated' && $e->product['stock'] === 4);
        Event::assertDispatched(ProductChanged::class, fn (ProductChanged $e) => $e->action === 'deleted' && $e->productId === $id && $e->product === null);
    }

    public function test_a_sync_product_change_publishes_nothing(): void
    {
        Event::fake([ProductChanged::class]);

        $this->actingAsApi($this->admin())
            ->postJson('/api/v4/sync/products', ['name' => 'Quiet item', 'price' => 3, 'cost' => 1, 'stock' => 2])
            ->assertCreated();

        Event::assertNotDispatched(ProductChanged::class);
    }

    public function test_the_async_cart_publishes_the_new_state_to_the_owner(): void
    {
        Event::fake([CartUpdated::class]);

        $product = $this->makeProduct(['stock' => 10, 'price' => 4]);
        $member = $this->member();
        $client = $this->actingAsApi($member);

        $client->postJson('/api/v4/async/cart/items', ['product_id' => $product->id, 'quantity' => 3])->assertOk();
        $client->putJson("/api/v4/async/cart/items/{$product->id}", ['quantity' => 5])->assertOk();
        $client->deleteJson("/api/v4/async/cart/items/{$product->id}")->assertOk();

        Event::assertDispatchedTimes(CartUpdated::class, 3);
        Event::assertDispatched(CartUpdated::class, fn (CartUpdated $e) => $e->userId === $member->id && $e->cart['total_quantity'] === 5);

        $event = new CartUpdated($member->id, ['items' => []]);
        $this->assertSame("private-cart.{$member->id}", $event->broadcastOn()[0]->name);
    }

    public function test_clearing_and_checkout_publish_an_empty_cart(): void
    {
        Event::fake([CartUpdated::class]);

        $product = $this->makeProduct(['stock' => 10]);
        $client = $this->actingAsApi($this->member());

        $client->postJson('/api/v4/sync/cart/items', ['product_id' => $product->id, 'quantity' => 1])->assertOk();
        $client->postJson('/api/v4/async/cart/checkout')->assertCreated();

        Event::assertDispatched(CartUpdated::class, fn (CartUpdated $e) => $e->cart['items'] === [] && $e->cart['total_quantity'] === 0);
    }

    public function test_the_gateway_asks_the_inventory_service_to_publish_only_in_async_mode(): void
    {
        $sync = $this->actingAsApi($this->member())->getJson('/api/v4/sync/cart');
        $async = $this->actingAsApi($this->member())->getJson('/api/v4/async/cart');

        $sync->assertHeader('X-Processing-Mode', 'sync');
        $async->assertHeader('X-Processing-Mode', 'async');
    }
}
