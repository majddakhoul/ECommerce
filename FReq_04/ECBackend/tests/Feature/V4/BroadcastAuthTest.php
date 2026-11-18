<?php

namespace Tests\Feature\V4;

use Illuminate\Support\Facades\Broadcast;
use Tests\ApiTestCase;
use Tests\Support\PusherLikeBroadcaster;

class BroadcastAuthTest extends ApiTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Broadcast::extend('testing', fn () => new PusherLikeBroadcaster);

        config([
            'broadcasting.default' => 'testing',
            'broadcasting.connections.testing' => ['driver' => 'testing'],
        ]);

        require base_path('routes/channels.php');
    }

    private function authorizeChannel($client, string $channel)
    {
        return $client->postJson('/api/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => $channel,
        ]);
    }

    public function test_the_owner_can_join_their_order_and_cart_channels(): void
    {
        $user = $this->member();
        $client = $this->actingAsApi($user);

        $this->authorizeChannel($client, "private-orders.user.{$user->id}")->assertOk()->assertJsonStructure(['auth']);
        $this->authorizeChannel($client, "private-cart.{$user->id}")->assertOk()->assertJsonStructure(['auth']);
    }

    public function test_another_user_cannot_join_someone_elses_channels(): void
    {
        $owner = $this->member();
        $client = $this->actingAsApi($this->member());

        $this->authorizeChannel($client, "private-orders.user.{$owner->id}")->assertForbidden();
        $this->authorizeChannel($client, "private-cart.{$owner->id}")->assertForbidden();
    }

    public function test_only_an_admin_can_join_the_admin_orders_channel(): void
    {
        $this->authorizeChannel($this->actingAsApi($this->member()), 'private-orders.admin')->assertForbidden();
        $this->authorizeChannel($this->actingAsApi($this->admin()), 'private-orders.admin')->assertOk();
    }

    public function test_an_admin_cannot_read_a_customer_cart_channel(): void
    {
        $owner = $this->member();

        $this->authorizeChannel($this->actingAsApi($this->admin()), "private-cart.{$owner->id}")->assertForbidden();
    }

    public function test_a_request_without_a_token_is_rejected(): void
    {
        $this->postJson('/api/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => 'private-orders.admin'])
            ->assertUnauthorized();
    }

    public function test_a_deactivated_user_is_rejected(): void
    {
        $user = $this->member();
        $client = $this->actingAsApi($user);
        $user->forceFill(['is_active' => false])->save();

        $this->authorizeChannel($client, "private-orders.user.{$user->id}")->assertStatus(403);
    }
}
