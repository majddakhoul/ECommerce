<?php

namespace Tests\Feature;

use App\Models\Product;
use Tests\ApiTestCase;

class CartTest extends ApiTestCase
{
    public function test_cart_requires_a_token(): void
    {
        $this->getJson($this->url('cart'))->assertStatus(401);
        $this->postJson($this->url('cart/items'), ['product_id' => 1, 'quantity' => 1])->assertStatus(401);
    }

    public function test_empty_cart_is_returned_for_a_new_user(): void
    {
        $this->actingAsApi($this->member())
            ->getJson($this->url('cart'))
            ->assertOk()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.total_quantity', 0)
            ->assertJsonPath('data.total_price', '0.00');
    }

    public function test_item_is_added_and_quantities_accumulate(): void
    {
        $product = Product::factory()->create(['price' => 10, 'stock' => 20]);
        $user = $this->member();

        $this->actingAsApi($user)
            ->postJson($this->url('cart/items'), ['product_id' => $product->id, 'quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->actingAsApi($user)
            ->postJson($this->url('cart/items'), ['product_id' => $product->id, 'quantity' => 3])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 5)
            ->assertJsonPath('data.total_quantity', 5)
            ->assertJsonPath('data.total_price', '50.00');
    }

    public function test_adding_more_than_the_stock_is_rejected(): void
    {
        $product = Product::factory()->create(['stock' => 3]);

        $this->actingAsApi($this->member())
            ->postJson($this->url('cart/items'), ['product_id' => $product->id, 'quantity' => 4])
            ->assertStatus(409);
    }

    public function test_add_validation_and_unknown_product(): void
    {
        $user = $this->member();

        $this->actingAsApi($user)
            ->postJson($this->url('cart/items'), ['product_id' => 9999, 'quantity' => 0])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['product_id', 'quantity']]);
    }

    public function test_item_quantity_is_updated(): void
    {
        $product = Product::factory()->create(['price' => 4, 'stock' => 10]);
        $user = $this->member();

        $this->actingAsApi($user)->postJson($this->url('cart/items'), ['product_id' => $product->id, 'quantity' => 1]);

        $this->actingAsApi($user)
            ->putJson($this->url("cart/items/{$product->id}"), ['quantity' => 6])
            ->assertOk()
            ->assertJsonPath('data.items.0.quantity', 6)
            ->assertJsonPath('data.total_price', '24.00');

        $this->actingAsApi($user)
            ->putJson($this->url("cart/items/{$product->id}"), ['quantity' => 11])
            ->assertStatus(409);
    }

    public function test_updating_or_removing_an_item_not_in_the_cart_fails(): void
    {
        $product = Product::factory()->create();
        $user = $this->member();

        $this->actingAsApi($user)->putJson($this->url("cart/items/{$product->id}"), ['quantity' => 1])->assertStatus(404);
        $this->actingAsApi($user)->deleteJson($this->url("cart/items/{$product->id}"))->assertStatus(404);
        $this->actingAsApi($user)->putJson($this->url('cart/items/9999'), ['quantity' => 1])
            ->assertStatus(404)
            ->assertJsonPath('message', 'Product not found.');
    }

    public function test_item_is_removed_and_cart_is_cleared(): void
    {
        $first = Product::factory()->create(['stock' => 10]);
        $second = Product::factory()->create(['stock' => 10]);
        $user = $this->member();

        $this->actingAsApi($user)->postJson($this->url('cart/items'), ['product_id' => $first->id, 'quantity' => 1]);
        $this->actingAsApi($user)->postJson($this->url('cart/items'), ['product_id' => $second->id, 'quantity' => 1]);

        $this->actingAsApi($user)
            ->deleteJson($this->url("cart/items/{$first->id}"))
            ->assertOk()
            ->assertJsonCount(1, 'data.items');

        $this->actingAsApi($user)->deleteJson($this->url('cart'))->assertNoContent();

        $this->actingAsApi($user)->getJson($this->url('cart'))->assertJsonCount(0, 'data.items');
    }

    public function test_carts_are_private_per_user(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $first = $this->member();
        $second = $this->member();

        $this->actingAsApi($first)->postJson($this->url('cart/items'), ['product_id' => $product->id, 'quantity' => 2]);

        $this->actingAsApi($second)->getJson($this->url('cart'))->assertJsonCount(0, 'data.items');
    }

    public function test_deleted_products_are_dropped_from_the_cart_view(): void
    {
        $product = Product::factory()->create(['stock' => 10]);
        $user = $this->member();

        $this->actingAsApi($user)->postJson($this->url('cart/items'), ['product_id' => $product->id, 'quantity' => 1]);
        $product->delete();

        $this->actingAsApi($user)->getJson($this->url('cart'))->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_checkout_creates_an_order_reduces_stock_and_clears_the_cart(): void
    {
        $first = Product::factory()->create(['price' => 10, 'stock' => 10]);
        $second = Product::factory()->create(['price' => 5, 'stock' => 4]);
        $user = $this->member();

        $this->actingAsApi($user)->postJson($this->url('cart/items'), ['product_id' => $first->id, 'quantity' => 2]);
        $this->actingAsApi($user)->postJson($this->url('cart/items'), ['product_id' => $second->id, 'quantity' => 4]);

        $this->actingAsApi($user)
            ->postJson($this->url('cart/checkout'))
            ->assertCreated()
            ->assertJsonPath('code', 201)
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.total_price', '40.00')
            ->assertJsonCount(2, 'data.items');

        $this->assertSame(8, $first->fresh()->stock);
        $this->assertSame(0, $second->fresh()->stock);

        $this->actingAsApi($user)->getJson($this->url('cart'))->assertJsonCount(0, 'data.items');
    }

    public function test_checkout_of_an_empty_cart_is_rejected(): void
    {
        $this->actingAsApi($this->member())
            ->postJson($this->url('cart/checkout'))
            ->assertStatus(422)
            ->assertJsonPath('message', 'The cart is empty.');
    }

    public function test_checkout_fails_without_changes_when_stock_ran_out(): void
    {
        $first = Product::factory()->create(['stock' => 10]);
        $second = Product::factory()->create(['stock' => 5]);
        $user = $this->member();

        $this->actingAsApi($user)->postJson($this->url('cart/items'), ['product_id' => $first->id, 'quantity' => 3]);
        $this->actingAsApi($user)->postJson($this->url('cart/items'), ['product_id' => $second->id, 'quantity' => 5]);

        $second->update(['stock' => 1]);

        $this->actingAsApi($user)->postJson($this->url('cart/checkout'))->assertStatus(409);

        $this->assertSame(10, $first->fresh()->stock);
        $this->assertSame(1, $second->fresh()->stock);
        $this->assertDatabaseCount('orders', 0);
        $this->actingAsApi($user)->getJson($this->url('cart'))->assertJsonCount(2, 'data.items');
    }
}
