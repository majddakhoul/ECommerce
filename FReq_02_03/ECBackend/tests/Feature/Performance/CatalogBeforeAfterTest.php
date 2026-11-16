<?php

namespace Tests\Feature\Performance;

use App\Models\Product;
use App\Support\Performance\Flags;
use Tests\ApiTestCase;

class CatalogBeforeAfterTest extends ApiTestCase
{
    private function v3(string $path = ''): string
    {
        return '/api/v3/' . ltrim($path, '/');
    }

    private function signIn(): void
    {
        $this->actingAsApi($this->admin());
    }

    private function seedCatalog(): Product
    {
        $this->makeProduct(['name' => 'Desk Lamp', 'description' => 'LED lamp', 'category' => 'home', 'stock' => 4]);
        $this->makeProduct(['name' => 'Wireless Mouse', 'description' => 'Ergonomic mouse', 'category' => 'electronics', 'stock' => 7]);

        return $this->makeProduct(['name' => 'Clean Code', 'description' => 'A handbook', 'category' => 'books', 'stock' => 2]);
    }

    public function test_v3_returns_the_same_catalog_as_v2(): void
    {
        $this->seedCatalog();
        $this->signIn();

        $before = $this->getJson($this->url('products'))->assertOk();
        $after = $this->getJson($this->v3('products'))->assertOk();

        $this->assertEquals($before->json('data'), $after->json('data'));
        $this->assertEquals($before->json('meta'), $after->json('meta'));
    }

    public function test_v3_search_and_category_match_v2(): void
    {
        $this->seedCatalog();
        $this->signIn();

        foreach (['products?search=lamp', 'products?category=books'] as $path) {
            $before = $this->getJson($this->url($path))->assertOk();
            $after = $this->getJson($this->v3($path))->assertOk();

            $this->assertEquals($before->json('data'), $after->json('data'), $path);
        }
    }

    public function test_v2_has_no_cache_headers_and_v3_does(): void
    {
        $this->seedCatalog();
        $this->signIn();

        $this->getJson($this->url('products'))->assertHeaderMissing('X-Cache');
        $this->getJson($this->v3('products'))
            ->assertHeader('X-Cache', 'MISS')
            ->assertHeader('X-DB-Queries')
            ->assertHeader('X-Response-Time-Ms');
    }

    public function test_second_v3_request_is_a_cache_hit_with_fewer_queries(): void
    {
        $this->seedCatalog();
        $this->signIn();

        $miss = $this->getJson($this->v3('products'))->assertHeader('X-Cache', 'MISS');
        $hit = $this->getJson($this->v3('products'))->assertHeader('X-Cache', 'HIT');

        $this->assertLessThan((int) $miss->headers->get('X-DB-Queries'), (int) $hit->headers->get('X-DB-Queries'));
        $this->assertSame('cache', $hit->headers->get('X-Data-Source'));
    }

    public function test_a_cache_hit_still_shows_live_stock(): void
    {
        $product = $this->seedCatalog();
        $this->signIn();

        $this->getJson($this->v3('products'));
        $this->inventory->set($product->id, 99);

        $hit = $this->getJson($this->v3('products'))->assertHeader('X-Cache', 'HIT');

        $row = collect($hit->json('data'))->firstWhere('id', $product->id);
        $this->assertSame(99, $row['stock']);
    }

    public function test_updating_a_product_invalidates_the_cache(): void
    {
        $product = $this->seedCatalog();
        $this->signIn();

        $this->getJson($this->v3('products'));
        $this->putJson($this->url("products/{$product->id}"), ['price' => 123.45])->assertOk();

        $fresh = $this->getJson($this->v3('products'))->assertHeader('X-Cache', 'MISS');

        $row = collect($fresh->json('data'))->firstWhere('id', $product->id);
        $this->assertEquals('123.45', $row['price']);
    }

    public function test_disabling_invalidation_exposes_stale_data(): void
    {
        $product = $this->seedCatalog();
        $this->signIn();
        app(Flags::class)->set('invalidation', false);

        $this->getJson($this->v3('products'));
        $this->putJson($this->url("products/{$product->id}"), ['price' => 123.45])->assertOk();

        $stale = $this->getJson($this->v3('products'))->assertHeader('X-Cache', 'HIT');

        $row = collect($stale->json('data'))->firstWhere('id', $product->id);
        $this->assertNotEquals('123.45', $row['price']);
    }

    public function test_cache_flag_off_never_hits(): void
    {
        $this->seedCatalog();
        $this->signIn();
        app(Flags::class)->set('cache', false);

        $this->getJson($this->v3('products'))->assertHeader('X-Cache', 'MISS');
        $this->getJson($this->v3('products'))->assertHeader('X-Cache', 'MISS');
    }

    public function test_in_stock_filter_bypasses_the_cache(): void
    {
        $product = $this->seedCatalog();
        $this->signIn();

        $this->getJson($this->v3('products?in_stock=1'))->assertHeader('X-Cache', 'BYPASS');
        $this->inventory->set($product->id, 0);

        $response = $this->getJson($this->v3('products?in_stock=1'))->assertOk();

        $this->assertNotContains($product->id, collect($response->json('data'))->pluck('id')->all());
    }

    public function test_etag_allows_a_304_until_the_stock_changes(): void
    {
        $product = $this->seedCatalog();
        $this->signIn();

        $first = $this->getJson($this->v3('products'))->assertOk();
        $etag = $first->headers->get('ETag');

        $this->assertNotEmpty($etag);
        $this->assertStringContainsString('no-cache', $first->headers->get('Cache-Control'));
        $this->assertStringNotContainsString('no-store', $first->headers->get('Cache-Control'));

        $this->getJson($this->v3('products'), ['If-None-Match' => $etag])->assertStatus(304);

        $this->inventory->set($product->id, 55);

        $changed = $this->getJson($this->v3('products'), ['If-None-Match' => $etag])->assertOk();
        $this->assertNotEquals($etag, $changed->headers->get('ETag'));
    }

    public function test_etag_flag_off_restores_no_store(): void
    {
        $this->seedCatalog();
        $this->signIn();
        app(Flags::class)->set('etag', false);

        $response = $this->getJson($this->v3('products'))->assertOk();

        $response->assertHeaderMissing('ETag');
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_v3_detail_is_cached_and_live(): void
    {
        $product = $this->seedCatalog();
        $this->signIn();

        $this->getJson($this->v3("products/{$product->id}"))->assertOk()->assertHeader('X-Cache', 'MISS');
        $this->inventory->set($product->id, 31);

        $this->getJson($this->v3("products/{$product->id}"))
            ->assertOk()
            ->assertHeader('X-Cache', 'HIT')
            ->assertJsonPath('data.stock', 31);
    }

    public function test_v3_detail_returns_404_for_unknown_product(): void
    {
        $this->signIn();

        $this->getJson($this->v3('products/999999'))->assertStatus(404);
    }

    public function test_deleting_a_product_invalidates_the_detail_cache(): void
    {
        $product = $this->seedCatalog();
        $this->signIn();

        $this->getJson($this->v3("products/{$product->id}"))->assertOk();
        $this->deleteJson($this->url("products/{$product->id}"))->assertStatus(204);

        $this->getJson($this->v3("products/{$product->id}"))->assertStatus(404);
    }
}
