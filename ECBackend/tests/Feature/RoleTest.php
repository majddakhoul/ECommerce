<?php

namespace Tests\Feature;

use Tests\ApiTestCase;

class RoleTest extends ApiTestCase
{
    public function test_built_in_roles_exist(): void
    {
        $this->actingAsApi($this->admin())
            ->getJson($this->url('roles'))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'admin')
            ->assertJsonPath('data.1.name', 'user');
    }

    public function test_regular_user_cannot_view_roles(): void
    {
        $this->actingAsApi($this->member())->getJson($this->url('roles'))->assertStatus(403);
        $this->actingAsApi($this->member())->getJson($this->url('roles/' . $this->role('user')->id))->assertStatus(403);
    }

    public function test_role_shows_users_count(): void
    {
        $this->member();

        $this->actingAsApi($this->admin())
            ->getJson($this->url('roles/' . $this->role('user')->id))
            ->assertOk()
            ->assertJsonPath('data.users_count', 1);
    }

    public function test_role_users_are_listed(): void
    {
        $this->member();

        $this->actingAsApi($this->admin())
            ->getJson($this->url('roles/' . $this->role('admin')->id . '/users'))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_missing_role_returns_role_not_found(): void
    {
        $admin = $this->admin();

        foreach (['roles/9999', 'roles/9999/users'] as $path) {
            $this->actingAsApi($admin)
                ->getJson($this->url($path))
                ->assertStatus(404)
                ->assertJsonPath('status', false)
                ->assertJsonPath('code', 404)
                ->assertJsonPath('message', 'Role not found.')
                ->assertJsonPath('data', null);
        }
    }

    public function test_missing_user_returns_user_not_found(): void
    {
        $this->actingAsApi($this->admin())
            ->getJson($this->url('users/9999'))
            ->assertStatus(404)
            ->assertJsonPath('code', 404)
            ->assertJsonPath('message', 'User not found.');
    }

    public function test_every_response_carries_status_code_message_and_data(): void
    {
        $this->actingAsApi($this->admin())
            ->getJson($this->url('roles'))
            ->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('code', 200)
            ->assertJsonStructure(['status', 'code', 'message', 'data']);
    }

    public function test_admin_creates_updates_and_deletes_a_role(): void
    {
        $admin = $this->admin();

        $id = $this->actingAsApi($admin)
            ->postJson($this->url('roles'), ['name' => 'Editor'])
            ->assertCreated()
            ->assertJsonPath('code', 201)
            ->assertJsonPath('data.name', 'editor')
            ->json('data.id');

        $this->actingAsApi($admin)
            ->putJson($this->url("roles/{$id}"), ['name' => 'reviewer'])
            ->assertOk()
            ->assertJsonPath('data.name', 'reviewer');

        $this->actingAsApi($admin)->deleteJson($this->url("roles/{$id}"))->assertNoContent();

        $this->actingAsApi($admin)->getJson($this->url("roles/{$id}"))->assertStatus(404);
    }

    public function test_role_name_must_be_valid_and_unique(): void
    {
        $admin = $this->admin();

        $this->actingAsApi($admin)->postJson($this->url('roles'), ['name' => 'admin'])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['name']]);

        $this->actingAsApi($admin)->postJson($this->url('roles'), ['name' => 'bad name!'])
            ->assertStatus(422);

        $this->actingAsApi($admin)->postJson($this->url('roles'), [])
            ->assertStatus(422);
    }

    public function test_built_in_roles_cannot_be_renamed_or_deleted(): void
    {
        $admin = $this->admin();

        foreach (['admin', 'user'] as $name) {
            $id = $this->role($name)->id;

            $this->actingAsApi($admin)->putJson($this->url("roles/{$id}"), ['name' => 'renamed'])->assertStatus(409);
            $this->actingAsApi($admin)->deleteJson($this->url("roles/{$id}"))->assertStatus(409);
        }
    }

    public function test_role_assigned_to_users_cannot_be_deleted(): void
    {
        $admin = $this->admin();
        $id = $this->actingAsApi($admin)->postJson($this->url('roles'), ['name' => 'editor'])->json('data.id');
        $user = $this->member();
        $user->assignRole('editor');

        $this->actingAsApi($admin)->deleteJson($this->url("roles/{$id}"))->assertStatus(409);
    }

    public function test_regular_user_cannot_manage_roles(): void
    {
        $user = $this->member();
        $id = $this->role('user')->id;

        $this->actingAsApi($user)->postJson($this->url('roles'), ['name' => 'x1'])->assertStatus(403);
        $this->actingAsApi($user)->putJson($this->url("roles/{$id}"), ['name' => 'x1'])->assertStatus(403);
        $this->actingAsApi($user)->deleteJson($this->url("roles/{$id}"))->assertStatus(403);
    }
}
