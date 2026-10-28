<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use Tests\ApiTestCase;

class OrderTest extends ApiTestCase
{
    private function placeOrder($user, array $items): array
    {
        return $this->actingAsApi($user)
            ->postJson($this->url('orders'), ['items' => $items])
            ->assertCreated()
            ->json('data');
    }

    public function test_orders_require_a_token(): void
    {
        $this->getJson($this->url('orders'))->assertStatus(401);
        $this->getJson($this->url('orders/my'))->assertStatus(401);
        $this->postJson($this->url('orders'), [])->assertStatus(401);
    }

    public function test_user_places_an_order_and_stock_is_reduced(): void
    {
        $product = Product::factory()->create(['price' => 12.50, 'stock' => 10]);

        $this->actingAsApi($this->member())
            ->postJson($this->url('orders'), ['items' => [['product_id' => $product->id, 'quantity' => 3]]])
            ->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('code', 201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_price', '37.50')
            ->assertJsonPath('data.items.0.product_name', $product->name)
            ->assertJsonPath('data.items.0.subtotal', '37.50');

        $this->assertSame(7, $product->fresh()->stock);
    }

    public function test_duplicate_lines_are_merged(): void
    {
        $product = Product::factory()->create(['price' => 2, 'stock' => 10]);

        $this->actingAsApi($this->member())
            ->postJson($this->url('orders'), ['items' => [
                ['product_id' => $product->id, 'quantity' => 1],
                ['product_id' => $product->id, 'quantity' => 2],
            ]])
            ->assertCreated()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.quantity', 3);
    }

    public function test_order_validation(): void
    {
        $this->actingAsApi($this->member())
            ->postJson($this->url('orders'), ['items' => [['product_id' => 9999, 'quantity' => 0]]])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items.0.product_id', 'items.0.quantity']]);

        $this->actingAsApi($this->member())
            ->postJson($this->url('orders'), [])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['items']]);
    }

    public function test_insufficient_stock_rejects_the_whole_order_without_changes(): void
    {
        $enough = Product::factory()->create(['stock' => 10]);
        $short = Product::factory()->create(['stock' => 1]);

        $this->actingAsApi($this->member())
            ->postJson($this->url('orders'), ['items' => [
                ['product_id' => $enough->id, 'quantity' => 4],
                ['product_id' => $short->id, 'quantity' => 2],
            ]])
            ->assertStatus(409);

        $this->assertSame(10, $enough->fresh()->stock);
        $this->assertSame(1, $short->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_user_sees_only_their_own_orders(): void
    {
        $mine = Order::factory()->create(['user_id' => $this->member()->id]);
        $user = $mine->user;
        $user->assignRole('user');
        Order::factory()->count(2)->create();

        $this->actingAsApi($user)
            ->getJson($this->url('orders/my'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $mine->id);
    }

    public function test_user_cannot_view_the_full_order_list(): void
    {
        $this->actingAsApi($this->member())->getJson($this->url('orders'))->assertStatus(403);
    }

    public function test_admin_lists_and_filters_all_orders(): void
    {
        $user = $this->member();
        Order::factory()->create(['user_id' => $user->id, 'status' => 'pending']);
        Order::factory()->create(['user_id' => $user->id, 'status' => 'delivered']);
        Order::factory()->create(['status' => 'pending']);

        $admin = $this->admin();

        $this->actingAsApi($admin)->getJson($this->url('orders'))->assertOk()->assertJsonPath('meta.total', 3);
        $this->actingAsApi($admin)->getJson($this->url('orders?status=pending'))->assertJsonPath('meta.total', 2);
        $this->actingAsApi($admin)->getJson($this->url("orders?user_id={$user->id}"))->assertJsonPath('meta.total', 2);
        $this->actingAsApi($admin)->getJson($this->url('orders?status=bogus'))->assertStatus(422);
    }

    public function test_user_cannot_access_someone_elses_order(): void
    {
        $order = Order::factory()->create();
        $stranger = $this->member();

        $this->actingAsApi($stranger)->getJson($this->url("orders/{$order->id}"))->assertStatus(403);
        $this->actingAsApi($stranger)->postJson($this->url("orders/{$order->id}/cancel"))->assertStatus(403);
        $this->actingAsApi($stranger)->putJson($this->url("orders/{$order->id}/status"), ['status' => 'confirmed'])->assertStatus(403);
        $this->actingAsApi($stranger)->deleteJson($this->url("orders/{$order->id}"))->assertStatus(403);
    }

    public function test_owner_and_admin_can_view_an_order(): void
    {
        $user = $this->member();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAsApi($user)->getJson($this->url("orders/{$order->id}"))->assertOk()->assertJsonPath('data.id', $order->id);
        $this->actingAsApi($this->admin())->getJson($this->url("orders/{$order->id}"))->assertOk();
    }

    public function test_missing_order_returns_order_not_found(): void
    {
        $this->actingAsApi($this->admin())
            ->getJson($this->url('orders/9999'))
            ->assertStatus(404)
            ->assertJsonPath('code', 404)
            ->assertJsonPath('message', 'Order not found.');
    }

    public function test_only_admin_changes_the_status(): void
    {
        $user = $this->member();
        $order = Order::factory()->create(['user_id' => $user->id]);

        $this->actingAsApi($user)
            ->putJson($this->url("orders/{$order->id}/status"), ['status' => 'confirmed'])
            ->assertStatus(403);

        $this->actingAsApi($this->admin())
            ->putJson($this->url("orders/{$order->id}/status"), ['status' => 'confirmed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'confirmed');
    }

    public function test_status_transitions_are_enforced_and_delivery_is_timestamped(): void
    {
        $order = Order::factory()->create();
        $admin = $this->admin();

        $this->actingAsApi($admin)->putJson($this->url("orders/{$order->id}/status"), ['status' => 'delivered'])->assertStatus(409);
        $this->actingAsApi($admin)->putJson($this->url("orders/{$order->id}/status"), ['status' => 'bogus'])->assertStatus(422);

        $this->actingAsApi($admin)->putJson($this->url("orders/{$order->id}/status"), ['status' => 'confirmed'])->assertOk();
        $this->actingAsApi($admin)->putJson($this->url("orders/{$order->id}/status"), ['status' => 'processing'])->assertOk();
        $this->actingAsApi($admin)
            ->putJson($this->url("orders/{$order->id}/status"), ['status' => 'delivered'])
            ->assertOk()
            ->assertJsonPath('data.status', 'delivered');

        $this->assertNotNull($order->fresh()->delivered_at);

        $this->actingAsApi($admin)->putJson($this->url("orders/{$order->id}/status"), ['status' => 'cancelled'])->assertStatus(409);
    }

    public function test_owner_cancels_an_order_and_stock_is_restored(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $user = $this->member();
        $order = $this->placeOrder($user, [['product_id' => $product->id, 'quantity' => 4]]);

        $this->assertSame(6, $product->fresh()->stock);

        $this->actingAsApi($user)
            ->postJson($this->url("orders/{$order['id']}/cancel"))
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled');

        $this->assertSame(10, $product->fresh()->stock);

        $this->actingAsApi($user)->postJson($this->url("orders/{$order['id']}/cancel"))->assertStatus(409);
        $this->assertSame(10, $product->fresh()->stock);
    }

    public function test_delivered_order_cannot_be_cancelled(): void
    {
        $user = $this->member();
        $order = Order::factory()->status('delivered')->create(['user_id' => $user->id]);

        $this->actingAsApi($user)->postJson($this->url("orders/{$order->id}/cancel"))->assertStatus(409);
    }

    public function test_admin_cancelling_through_the_status_endpoint_restores_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = $this->placeOrder($this->member(), [['product_id' => $product->id, 'quantity' => 5]]);

        $this->actingAsApi($this->admin())
            ->putJson($this->url("orders/{$order['id']}/status"), ['status' => 'cancelled'])
            ->assertOk();

        $this->assertSame(5, $product->fresh()->stock);
    }

    public function test_admin_deletes_an_order_and_open_orders_restore_stock(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $order = $this->placeOrder($this->member(), [['product_id' => $product->id, 'quantity' => 2]]);

        $this->actingAsApi($this->admin())->deleteJson($this->url("orders/{$order['id']}"))->assertNoContent();

        $this->assertNull(Order::find($order['id']));
        $this->assertSame(5, $product->fresh()->stock);
        $this->assertDatabaseCount('order_items', 0);
    }

    public function test_deleting_a_product_keeps_the_order_history(): void
    {
        $product = Product::factory()->create(['stock' => 5]);
        $user = $this->member();
        $order = $this->placeOrder($user, [['product_id' => $product->id, 'quantity' => 1]]);

        $this->actingAsApi($this->admin())->deleteJson($this->url("products/{$product->id}"))->assertNoContent();

        $this->actingAsApi($user)
            ->getJson($this->url("orders/{$order['id']}"))
            ->assertOk()
            ->assertJsonPath('data.items.0.product_id', null)
            ->assertJsonPath('data.items.0.product_name', $product->name);
    }

    public function test_every_api_response_carries_performance_headers(): void
    {
        $response = $this->actingAsApi($this->member())->getJson($this->url('cart'));

        $response->assertOk();
        $response->assertHeader('X-Trace-Id');
        $response->assertHeader('X-Response-Time-ms');
        $response->assertHeader('X-Memory-Used-kb');
    }
}
