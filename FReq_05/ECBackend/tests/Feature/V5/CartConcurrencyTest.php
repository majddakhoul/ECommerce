<?php

namespace Tests\Feature\V5;

use Illuminate\Support\Facades\Cache;
use Tests\ApiTestCase;

class CartConcurrencyTest extends ApiTestCase
{
    public function test_a_cart_change_is_rejected_while_another_change_holds_the_lock(): void
    {
        config(['api.cart_lock_wait_seconds' => 0]);

        $product = $this->makeProduct(['stock' => 10]);
        $user = $this->member();

        $holder = Cache::lock('cart:lock:' . $user->id, 30);
        $this->assertTrue($holder->get());

        $this->actingAsApi($user)
            ->postJson('/api/v4/sync/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertStatus(409);

        $holder->release();

        $this->actingAsApi($user)
            ->postJson('/api/v4/sync/cart/items', ['product_id' => $product->id, 'quantity' => 1])
            ->assertOk()
            ->assertJsonPath('data.total_quantity', 1);
    }

    public function test_the_cart_seen_by_one_request_includes_the_change_of_the_previous_one(): void
    {
        $first = $this->makeProduct(['price' => 4, 'stock' => 10]);
        $second = $this->makeProduct(['price' => 6, 'stock' => 10]);
        $user = $this->member();

        $this->actingAsApi($user)
            ->postJson('/api/v4/async/cart/items', ['product_id' => $first->id, 'quantity' => 1])
            ->assertOk();

        $this->actingAsApi($user)
            ->postJson('/api/v4/sync/cart/items', ['product_id' => $second->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.total_quantity', 3);

        $this->actingAsApi($user)
            ->getJson('/api/v4/async/cart')
            ->assertOk()
            ->assertJsonPath('data.total_quantity', 3)
            ->assertJsonPath('data.total_price', '16.00');
    }
}
