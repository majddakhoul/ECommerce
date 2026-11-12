<?php

namespace Tests\Feature;

use App\Models\Product;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\ApiTestCase;

class ProductTest extends ApiTestCase
{
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Test Product',
            'description' => 'A product',
            'price' => 19.99,
            'cost' => 10,
            'stock' => 5,
            'category' => 'Books',
        ], $overrides);
    }

    public function test_products_require_a_token(): void
    {
        $this->getJson($this->url('products'))->assertStatus(401);
        $this->postJson($this->url('products'), $this->payload())->assertStatus(401);
    }

    public function test_admin_creates_a_product_and_category_is_normalized(): void
    {
        $this->actingAsApi($this->admin())
            ->postJson($this->url('products'), $this->payload())
            ->assertCreated()
            ->assertJsonPath('code', 201)
            ->assertJsonPath('data.name', 'Test Product')
            ->assertJsonPath('data.category', 'books')
            ->assertJsonPath('data.in_stock', true)
            ->assertJsonPath('data.image', null);

        $this->assertDatabaseHas('products', ['name' => 'Test Product']);
    }

    public function test_regular_user_cannot_create_update_or_delete(): void
    {
        $user = $this->member();
        $product = Product::factory()->create();

        $this->actingAsApi($user)->postJson($this->url('products'), $this->payload())->assertStatus(403);
        $this->actingAsApi($user)->putJson($this->url("products/{$product->id}"), ['name' => 'X'])->assertStatus(403);
        $this->actingAsApi($user)->deleteJson($this->url("products/{$product->id}"))->assertStatus(403);
    }

    public function test_regular_user_can_list_and_view(): void
    {
        $product = Product::factory()->create();

        $this->actingAsApi($this->member())->getJson($this->url('products'))->assertOk();
        $this->actingAsApi($this->member())
            ->getJson($this->url("products/{$product->id}"))
            ->assertOk()
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_create_validation_uses_the_unified_error_format(): void
    {
        $this->actingAsApi($this->admin())
            ->postJson($this->url('products'), ['price' => -1, 'stock' => 'abc'])
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonStructure(['errors' => ['name', 'price', 'stock']]);
    }

    public function test_listing_is_paginated(): void
    {
        Product::factory()->count(5)->create();

        $this->actingAsApi($this->member())
            ->getJson($this->url('products?per_page=2'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.total', 5)
            ->assertJsonPath('meta.last_page', 3);
    }

    public function test_search_filters_by_name_and_description(): void
    {
        Product::factory()->create(['name' => 'Blue Lamp', 'description' => 'Desk item']);
        Product::factory()->create(['name' => 'Red Chair', 'description' => 'Has a lamp holder']);
        Product::factory()->create(['name' => 'Green Table', 'description' => 'Wooden']);

        $this->actingAsApi($this->member())
            ->getJson($this->url('products?search=lamp'))
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_search_treats_wildcards_literally(): void
    {
        Product::factory()->create(['name' => 'Plain', 'description' => 'text']);

        $this->actingAsApi($this->member())
            ->getJson($this->url('products?search=%25'))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_filter_by_category_and_stock(): void
    {
        Product::factory()->create(['category' => 'books', 'stock' => 3]);
        Product::factory()->create(['category' => 'books', 'stock' => 0]);
        Product::factory()->create(['category' => 'home', 'stock' => 9]);

        $user = $this->member();

        $this->actingAsApi($user)->getJson($this->url('products?category=Books'))->assertJsonCount(2, 'data');
        $this->actingAsApi($user)->getJson($this->url('products?category=books&in_stock=1'))->assertJsonCount(1, 'data');
        $this->actingAsApi($user)->getJson($this->url('products?in_stock=0'))->assertJsonCount(1, 'data');
    }

    public function test_categories_are_listed_without_duplicates(): void
    {
        Product::factory()->create(['category' => 'books']);
        Product::factory()->create(['category' => 'books']);
        Product::factory()->create(['category' => 'home']);
        Product::factory()->create(['category' => null]);

        $this->actingAsApi($this->member())
            ->getJson($this->url('products/categories'))
            ->assertOk()
            ->assertJsonPath('data', ['books', 'home']);
    }

    public function test_admin_updates_a_product_partially(): void
    {
        $product = Product::factory()->create(['name' => 'Old', 'stock' => 1]);

        $this->actingAsApi($this->admin())
            ->putJson($this->url("products/{$product->id}"), ['name' => 'New', 'stock' => 7])
            ->assertOk()
            ->assertJsonPath('data.name', 'New')
            ->assertJsonPath('data.stock', 7);
    }

    public function test_admin_deletes_a_product(): void
    {
        $product = Product::factory()->create();

        $this->actingAsApi($this->admin())->deleteJson($this->url("products/{$product->id}"))->assertNoContent();

        $this->assertNull(Product::find($product->id));
    }

    public function test_missing_product_returns_product_not_found(): void
    {
        $admin = $this->admin();

        $this->actingAsApi($admin)
            ->getJson($this->url('products/9999'))
            ->assertStatus(404)
            ->assertJsonPath('code', 404)
            ->assertJsonPath('message', 'Product not found.');

        $this->actingAsApi($admin)->putJson($this->url('products/9999'), ['name' => 'X'])->assertStatus(404);
        $this->actingAsApi($admin)->deleteJson($this->url('products/9999'))->assertStatus(404);
    }

    public function test_image_is_uploaded_replaced_and_removed_on_delete(): void
    {
        Storage::fake('public');
        $admin = $this->admin();

        $id = $this->actingAsApi($admin)
            ->post($this->url('products'), $this->payload(['image' => UploadedFile::fake()->image('a.jpg')]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.id');

        $first = Product::find($id)->image;
        Storage::disk('public')->assertExists($first);

        $this->actingAsApi($admin)
            ->post($this->url("products/{$id}"), ['_method' => 'PUT', 'image' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])
            ->assertOk();

        $second = Product::find($id)->image;
        Storage::disk('public')->assertMissing($first);
        Storage::disk('public')->assertExists($second);

        $this->actingAsApi($admin)->deleteJson($this->url("products/{$id}"))->assertNoContent();
        Storage::disk('public')->assertMissing($second);
    }

    public function test_non_image_upload_is_rejected(): void
    {
        Storage::fake('public');

        $this->actingAsApi($this->admin())
            ->post($this->url('products'), $this->payload(['image' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')]), ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonStructure(['errors' => ['image']]);
    }
}
