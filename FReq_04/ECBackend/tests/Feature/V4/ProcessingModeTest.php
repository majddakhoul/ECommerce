<?php

namespace Tests\Feature\V4;

use Tests\ApiTestCase;

class ProcessingModeTest extends ApiTestCase
{
    public function test_sync_routes_report_the_sync_mode(): void
    {
        $product = $this->makeProduct();

        $this->actingAsApi($this->member())
            ->getJson("/api/v4/sync/products/{$product->id}")
            ->assertOk()
            ->assertHeader('X-Processing-Mode', 'sync')
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_async_routes_report_the_async_mode(): void
    {
        $product = $this->makeProduct();

        $this->actingAsApi($this->member())
            ->getJson("/api/v4/async/products/{$product->id}")
            ->assertOk()
            ->assertHeader('X-Processing-Mode', 'async')
            ->assertJsonPath('data.id', $product->id);
    }

    public function test_earlier_versions_do_not_carry_the_processing_header(): void
    {
        $product = $this->makeProduct();

        $this->actingAsApi($this->member())
            ->getJson("/api/v2/products/{$product->id}")
            ->assertOk()
            ->assertHeaderMissing('X-Processing-Mode');
    }

    public function test_both_modes_expose_the_authentication_endpoints(): void
    {
        foreach (['sync', 'async'] as $mode) {
            $this->postJson("/api/v4/{$mode}/auth/login", ['email' => 'nobody@example.com', 'password' => 'wrong-password-1'])
                ->assertStatus(401)
                ->assertHeader('X-Processing-Mode', $mode);
        }
    }

    public function test_the_bare_v4_prefix_does_not_exist(): void
    {
        $this->getJson('/api/v4/products')->assertStatus(404);
    }
}
