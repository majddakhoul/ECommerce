<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\ApiTestCase;

class AuthTest extends ApiTestCase
{
    public function test_register_creates_a_user_with_the_default_role_and_returns_a_token(): void
    {
        $this->postJson($this->url('auth/register'), [
            'name' => 'Sara',
            'email' => 'Sara@Example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertCreated()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.user.email', 'sara@example.com')
            ->assertJsonPath('data.user.roles.0', 'user')
            ->assertJsonStructure(['data' => ['access_token', 'token_type', 'expires_in']]);
    }

    public function test_register_cannot_escalate_privileges(): void
    {
        $this->postJson($this->url('auth/register'), [
            'name' => 'Eve',
            'email' => 'eve@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'role' => 'admin',
            'role_ids' => [1],
            'is_active' => false,
        ])->assertCreated()->assertJsonPath('data.user.roles.0', 'user');

        $this->assertTrue(User::where('email', 'eve@example.com')->first()->is_active);
    }

    public function test_register_validation_uses_the_unified_error_format(): void
    {
        $this->postJson($this->url('auth/register'), ['email' => 'bad', 'password' => 'short'])
            ->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('message', 'Validation failed')
            ->assertJsonStructure(['errors' => ['name', 'email', 'password']]);
    }

    public function test_register_rejects_duplicate_email(): void
    {
        $this->postJson($this->url('auth/register'), [
            'name' => 'Dup',
            'email' => 'admin@example.com',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
        ])->assertStatus(422)->assertJsonStructure(['errors' => ['email']]);
    }

    public function test_login_with_valid_credentials_returns_a_token(): void
    {
        $this->postJson($this->url('auth/login'), ['email' => 'admin@example.com', 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.roles', ['admin'])
            ->assertJsonStructure(['data' => ['user', 'access_token', 'token_type', 'expires_in']]);

        $this->assertNotNull($this->admin()->last_login_at);
    }

    public function test_login_returns_the_roles_of_a_regular_user(): void
    {
        $user = $this->member();

        $this->postJson($this->url('auth/login'), ['email' => $user->email, 'password' => 'password'])
            ->assertOk()
            ->assertJsonPath('data.user.roles', ['user']);
    }

    public function test_login_with_wrong_password_is_rejected_without_leaking_details(): void
    {
        $this->postJson($this->url('auth/login'), ['email' => 'admin@example.com', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials');

        $this->postJson($this->url('auth/login'), ['email' => 'ghost@example.com', 'password' => 'wrong'])
            ->assertStatus(401)
            ->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_disabled_account_cannot_log_in(): void
    {
        $user = User::factory()->inactive()->create();

        $this->postJson($this->url('auth/login'), ['email' => $user->email, 'password' => 'password'])
            ->assertStatus(403);
    }

    public function test_existing_token_stops_working_when_the_account_is_disabled(): void
    {
        $user = $this->member();

        $this->actingAsApi($user)->getJson($this->url('auth/me'))->assertOk();

        $user->update(['is_active' => false]);

        $this->actingAsApi($user->fresh())->getJson($this->url('auth/me'))->assertStatus(403);
    }

    public function test_protected_routes_require_a_token(): void
    {
        $this->getJson($this->url('auth/me'))->assertStatus(401)->assertJsonPath('status', false);
        $this->getJson($this->url('users'))->assertStatus(401);
        $this->getJson($this->url('roles'))->assertStatus(401);
    }

    public function test_invalid_token_is_rejected(): void
    {
        $this->withToken('not-a-real-token')->getJson($this->url('auth/me'))->assertStatus(401);
    }

    public function test_me_returns_roles(): void
    {
        $this->actingAsApi($this->member())
            ->getJson($this->url('auth/me'))
            ->assertOk()
            ->assertJsonPath('data.roles', ['user']);
    }

    public function test_user_updates_own_profile(): void
    {
        $user = $this->member();

        $this->actingAsApi($user)
            ->putJson($this->url('auth/me'), ['name' => 'New Name', 'is_active' => false, 'role' => 'admin'])
            ->assertOk()
            ->assertJsonPath('data.name', 'New Name');

        $this->assertTrue($user->fresh()->is_active);
        $this->assertFalse($user->fresh()->hasRole('admin'));
    }

    public function test_change_password_requires_the_current_password(): void
    {
        $this->actingAsApi($this->member())
            ->putJson($this->url('auth/password'), [
                'current_password' => 'wrong',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])->assertStatus(422)->assertJsonStructure(['errors' => ['current_password']]);
    }

    public function test_change_password_returns_a_new_token_and_updates_the_password(): void
    {
        $user = $this->member();

        $this->actingAsApi($user)
            ->putJson($this->url('auth/password'), [
                'current_password' => 'password',
                'password' => 'newsecret123',
                'password_confirmation' => 'newsecret123',
            ])->assertOk()->assertJsonStructure(['data' => ['access_token']]);

        $this->postJson($this->url('auth/login'), ['email' => $user->email, 'password' => 'newsecret123'])
            ->assertOk();
    }

    public function test_new_password_must_differ_from_the_current_one(): void
    {
        $this->actingAsApi($this->member())
            ->putJson($this->url('auth/password'), [
                'current_password' => 'password',
                'password' => 'password',
                'password_confirmation' => 'password',
            ])->assertStatus(422);
    }

    public function test_logout_invalidates_the_token(): void
    {
        $this->actingAsApi($this->member())->postJson($this->url('auth/logout'))->assertOk();

        $this->app['auth']->forgetGuards();

        $this->getJson($this->url('auth/me'))->assertStatus(401);
    }

    public function test_refresh_returns_a_new_token(): void
    {
        $this->actingAsApi($this->member())
            ->postJson($this->url('auth/refresh'))
            ->assertOk()
            ->assertJsonStructure(['data' => ['access_token']]);
    }

    public function test_unknown_endpoint_returns_json_404(): void
    {
        $this->getJson('/api/v1/nope')->assertStatus(404)->assertJsonPath('status', false);
    }

    public function test_security_headers_are_present(): void
    {
        $this->getJson($this->url('auth/me'))
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
