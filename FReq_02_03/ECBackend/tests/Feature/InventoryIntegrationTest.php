<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Repositories\Contracts\OrderRepositoryInterface;
use Tests\ApiTestCase;

class InventoryIntegrationTest extends ApiTestCase
{
    private function order($user, $product, int $quantity): array
    {
        return $this->actingAsApi($user)
            ->postJson($this->url('orders'), ['items' => [['product_id' => $product->id, 'quantity' => $quantity]]])
            ->assertCreated()
            ->json('data');
    }

    public function test_product_list_and_details_show_the_quantity_held_by_the_inventory_service(): void
    {
        $product = $this->makeProduct(['stock' => 12]);
        $empty = $this->makeProduct(['stock' => 0]);
        $user = $this->member();

        $this->actingAsApi($user)
            ->getJson($this->url("products/{$product->id}"))
            ->assertOk()
            ->assertJsonPath('data.stock', 12)
            ->assertJsonPath('data.in_stock', true);

        $list = $this->actingAsApi($user)->getJson($this->url('products'))->assertOk()->json('data');

        $this->assertSame([12, 0], array_column($list, 'stock'));
        $this->assertSame([true, false], array_column($list, 'in_stock'));
        $this->assertSame($empty->id, $list[1]['id']);
    }

    public function test_the_products_table_no_longer_stores_stock(): void
    {
        $this->assertFalse(\Illuminate\Support\Facades\Schema::hasColumn('products', 'stock'));
    }

    public function test_creating_a_product_registers_its_quantity_in_the_inventory_service(): void
    {
        $response = $this->actingAsApi($this->admin())
            ->postJson($this->url('products'), ['name' => 'Lamp', 'price' => 9, 'stock' => 15])
            ->assertCreated()
            ->assertJsonPath('data.stock', 15);

        $this->assertSame(15, $this->inventory->stock($response->json('data.id')));
    }

    public function test_updating_the_stock_of_a_product_goes_through_the_inventory_service(): void
    {
        $product = $this->makeProduct(['stock' => 2]);

        $this->actingAsApi($this->admin())
            ->putJson($this->url("products/{$product->id}"), ['stock' => 40])
            ->assertOk()
            ->assertJsonPath('data.stock', 40);

        $this->assertSame(40, $this->stockOf($product));
    }

    public function test_deleting_a_product_removes_its_quantity_from_the_inventory_service(): void
    {
        $product = $this->makeProduct(['stock' => 3]);

        $this->actingAsApi($this->admin())->deleteJson($this->url("products/{$product->id}"))->assertNoContent();

        $this->assertFalse($this->inventory->has($product->id));
    }

    public function test_product_creation_is_rolled_back_when_the_inventory_service_is_down(): void
    {
        $this->inventory->unavailable = true;

        $this->actingAsApi($this->admin())
            ->postJson($this->url('products'), ['name' => 'Ghost', 'price' => 9, 'stock' => 5])
            ->assertStatus(503);

        $this->assertDatabaseMissing('products', ['name' => 'Ghost']);
    }

    public function test_in_stock_filter_uses_the_inventory_service(): void
    {
        $this->makeProduct(['stock' => 4]);
        $this->makeProduct(['stock' => 0]);
        $user = $this->member();

        $this->actingAsApi($user)->getJson($this->url('products?in_stock=1'))->assertJsonCount(1, 'data');
        $this->actingAsApi($user)->getJson($this->url('products?in_stock=0'))->assertJsonCount(1, 'data');
    }

    public function test_placing_an_order_reserves_the_quantity_and_keeps_the_reservation_id(): void
    {
        $product = $this->makeProduct(['stock' => 10]);

        $order = $this->order($this->member(), $product, 4);

        $this->assertSame(6, $this->stockOf($product));

        $reservationId = Order::findOrFail($order['id'])->reservation_id;

        $this->assertNotNull($reservationId);
        $this->assertSame('reserved', $this->inventory->reservationStatus($reservationId));
    }

    public function test_an_order_is_rejected_with_a_clear_message_when_stock_is_not_enough(): void
    {
        $product = $this->makeProduct(['name' => 'Rare Item', 'stock' => 2]);

        $this->actingAsApi($this->member())
            ->postJson($this->url('orders'), ['items' => [['product_id' => $product->id, 'quantity' => 5]]])
            ->assertStatus(409)
            ->assertJsonPath('message', "Insufficient stock for 'Rare Item'. Requested: 5, available: 2.");

        $this->assertDatabaseCount('orders', 0);
        $this->assertSame(2, $this->stockOf($product));
    }

    public function test_an_order_is_rejected_with_503_when_the_inventory_service_is_down(): void
    {
        $product = $this->makeProduct(['stock' => 10]);
        $this->inventory->unavailable = true;

        $this->actingAsApi($this->member())
            ->postJson($this->url('orders'), ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
            ->assertStatus(503)
            ->assertJsonPath('status', false)
            ->assertJsonPath('code', 503);

        $this->assertDatabaseCount('orders', 0);
    }

    public function test_the_reservation_is_released_when_the_order_cannot_be_saved(): void
    {
        $product = $this->makeProduct(['stock' => 10]);

        $this->mock(OrderRepositoryInterface::class, function ($mock) {
            $mock->shouldReceive('create')->andThrow(new \RuntimeException('database down'));
        });

        $this->actingAsApi($this->member())
            ->postJson($this->url('orders'), ['items' => [['product_id' => $product->id, 'quantity' => 4]]])
            ->assertStatus(500);

        $this->assertSame(10, $this->stockOf($product));
    }

    public function test_cancelling_an_order_releases_its_reservation_once(): void
    {
        $product = $this->makeProduct(['stock' => 10]);
        $user = $this->member();
        $order = $this->order($user, $product, 4);
        $reservationId = Order::findOrFail($order['id'])->reservation_id;

        $this->actingAsApi($user)->postJson($this->url("orders/{$order['id']}/cancel"))->assertOk();

        $this->assertSame(10, $this->stockOf($product));
        $this->assertSame('released', $this->inventory->reservationStatus($reservationId));
    }

    public function test_cancelling_keeps_the_order_open_when_the_inventory_service_is_down(): void
    {
        $product = $this->makeProduct(['stock' => 10]);
        $user = $this->member();
        $order = $this->order($user, $product, 4);

        $this->inventory->unavailable = true;

        $this->actingAsApi($user)->postJson($this->url("orders/{$order['id']}/cancel"))->assertStatus(503);

        $this->assertSame('pending', Order::findOrFail($order['id'])->status);
    }

    public function test_delivered_orders_keep_their_reservation(): void
    {
        $product = $this->makeProduct(['stock' => 10]);
        $order = $this->order($this->member(), $product, 3);

        foreach (['confirmed', 'delivered'] as $status) {
            $this->actingAsApi($this->admin())
                ->putJson($this->url("orders/{$order['id']}/status"), ['status' => $status])
                ->assertOk();
        }

        $this->assertSame(7, $this->stockOf($product));
    }

    public function test_cart_shows_the_available_stock_from_the_inventory_service(): void
    {
        $product = $this->makeProduct(['stock' => 9]);
        $user = $this->member();

        $this->actingAsApi($user)
            ->postJson($this->url('cart/items'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.items.0.available_stock', 9);
    }
}
