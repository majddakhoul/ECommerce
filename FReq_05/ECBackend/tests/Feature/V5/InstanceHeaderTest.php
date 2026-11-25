<?php

namespace Tests\Feature\V5;

use Tests\ApiTestCase;

class InstanceHeaderTest extends ApiTestCase
{
    public function test_every_response_names_the_instance_that_served_it(): void
    {
        config(['app.instance' => 'app2']);
        $this->actingAsApi($this->member());

        $this->getJson('/api/v4/sync/products')->assertOk()->assertHeader('X-Served-By', 'app2');
        $this->getJson('/api/v4/async/products')->assertOk()->assertHeader('X-Served-By', 'app2');
    }

    public function test_error_responses_name_the_instance_too(): void
    {
        config(['app.instance' => 'app3']);

        $this->getJson('/api/v4/sync/cart')->assertStatus(401)->assertHeader('X-Served-By', 'app3');
    }

    public function test_the_header_is_exposed_to_browsers(): void
    {
        $this->assertContains('X-Served-By', config('cors.exposed_headers'));
    }

    public function test_the_instance_name_falls_back_to_the_host_name(): void
    {
        $this->assertNotSame('', (string) config('app.instance'));
    }
}
