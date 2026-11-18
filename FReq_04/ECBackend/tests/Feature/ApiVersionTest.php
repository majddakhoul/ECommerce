<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class ApiVersionTest extends ApiTestCase
{
    public function test_both_versions_return_the_same_products_with_stock(): void
    {
        $product = $this->makeProduct(['name' => 'Versioned', 'stock' => 7]);
        $admin = $this->actingAsApi($this->admin());

        foreach (['v1', 'v2'] as $version) {
            $admin->getJson("/api/{$version}/products/{$product->id}")
                ->assertOk()
                ->assertJsonPath('data.name', 'Versioned')
                ->assertJsonPath('data.stock', 7);
        }
    }

    public function test_orders_are_accepted_under_both_versions(): void
    {
        $product = $this->makeProduct(['stock' => 4]);
        $member = $this->actingAsApi($this->member());

        foreach (['v1', 'v2'] as $version) {
            $member->postJson("/api/{$version}/orders", ['items' => [['product_id' => $product->id, 'quantity' => 1]]])
                ->assertCreated();
        }

        $this->assertSame(2, $this->stockOf($product));
    }

    public function test_unknown_version_returns_the_json_404(): void
    {
        $this->getJson('/api/v9/products')->assertStatus(404)->assertJsonPath('status', false);
    }
}
