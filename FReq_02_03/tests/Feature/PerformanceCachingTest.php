<?php
namespace Tests\Feature;
use App\Models\Product;
use Tests\ApiTestCase;
class PerformanceCachingTest extends ApiTestCase
{
    public function test_product_reads_return_an_etag_and_support_conditional_requests(): void
    {
        Product::factory()->create(['name' => 'Cached product']);
        $user = $this->member();
        $first = $this->actingAsApi($user)->getJson($this->url('products'));
        $first->assertOk()->assertHeader('ETag');
        $etag = $first->headers->get('ETag');
        $this->actingAsApi($user)->withHeaders(['If-None-Match' => $etag])->getJson($this->url('products'))->assertStatus(304);
    }
    public function test_product_mutation_changes_the_etag(): void
    {
        Product::factory()->create(['name' => 'Before']);
        $user = $this->member();
        $first = $this->actingAsApi($user)->getJson($this->url('products'));
        $etag = $first->headers->get('ETag');
        Product::factory()->create(['name' => 'After']);
        $this->actingAsApi($user)->withHeaders(['If-None-Match' => $etag])->getJson($this->url('products'))->assertOk()->assertHeader('ETag');
    }
}
