<?php

namespace Tests\Feature\Performance;

use App\Models\Order;
use App\Models\OrderItem;
use App\Support\Performance\Flags;
use Tests\ApiTestCase;

class PerformanceLabTest extends ApiTestCase
{
    private function lab(string $path = ''): string
    {
        return '/api/v3/performance/' . ltrim($path, '/');
    }

    public function test_only_administrators_can_use_the_lab(): void
    {
        $this->actingAsApi($this->member());

        $this->getJson($this->lab('status'))->assertStatus(403);
        $this->getJson($this->lab('compare?scenario=list'))->assertStatus(403);
        $this->putJson($this->lab('flags'), ['cache' => false])->assertStatus(403);
    }

    public function test_status_reports_flags_counts_and_cache_version(): void
    {
        $this->makeProduct();
        $this->actingAsApi($this->admin());

        $this->getJson($this->lab('status'))
            ->assertOk()
            ->assertJsonPath('data.flags.cache', true)
            ->assertJsonPath('data.tables.products', 1)
            ->assertJsonPath('data.replica.configured', false);
    }

    public function test_flags_can_be_changed_and_reset(): void
    {
        $this->actingAsApi($this->admin());

        $this->putJson($this->lab('flags'), ['cache' => false, 'etag' => false])
            ->assertOk()
            ->assertJsonPath('data.cache', false)
            ->assertJsonPath('data.etag', false);

        $this->assertFalse(app(Flags::class)->enabled('cache'));

        $this->deleteJson($this->lab('flags'))->assertOk()->assertJsonPath('data.cache', true);
    }

    public function test_flags_reject_unknown_values(): void
    {
        $this->actingAsApi($this->admin());

        $this->putJson($this->lab('flags'), ['cache' => 'maybe'])->assertStatus(422);
    }

    public function test_flushing_the_cache_bumps_its_version(): void
    {
        $this->actingAsApi($this->admin());

        $before = $this->getJson($this->lab('status'))->json('data.cache.version');
        $after = $this->postJson($this->lab('cache/flush'))->assertOk()->json('data.version');

        $this->assertGreaterThan($before, $after);
    }

    public function test_compare_shows_the_n_plus_one_difference(): void
    {
        $this->makeProduct();

        foreach (Order::factory()->count(5)->create() as $order) {
            OrderItem::create([
                'order_id' => $order->id,
                'product_id' => null,
                'product_name' => 'Line',
                'quantity' => 1,
                'price' => 10,
            ]);
        }

        $this->actingAsApi($this->admin());

        $result = $this->getJson($this->lab('compare?scenario=orders&runs=3'))->assertOk();

        $this->assertGreaterThan(
            $result->json('data.after.avg_queries'),
            $result->json('data.before.avg_queries')
        );
        $this->assertGreaterThan(0, $result->json('data.queries_saved'));
    }

    public function test_compare_list_shows_the_cache_effect(): void
    {
        $this->makeProduct();
        $this->makeProduct();
        $this->actingAsApi($this->admin());

        $result = $this->getJson($this->lab('compare?scenario=list&runs=5'))->assertOk();

        $this->assertSame(1.0, (float) $result->json('data.after.cache_hit_ratio'));
        $this->assertLessThan(
            $result->json('data.before.avg_queries'),
            $result->json('data.after.avg_queries')
        );
    }

    public function test_compare_rejects_unknown_scenarios_and_excessive_runs(): void
    {
        $this->actingAsApi($this->admin());

        $this->getJson($this->lab('compare?scenario=nope'))->assertStatus(422);
        $this->getJson($this->lab('compare?scenario=list&runs=100000'))->assertStatus(422);
    }

    public function test_explain_returns_a_plan(): void
    {
        $this->makeProduct(['category' => 'home']);
        $this->actingAsApi($this->admin());

        $this->getJson($this->lab('explain?scenario=category&category=home'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['scenario', 'sql', 'plan']]);
    }
}
