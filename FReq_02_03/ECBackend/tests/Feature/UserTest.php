<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\ApiTestCase;

class UserTest extends ApiTestCase
{
    public function test_regular_user_cannot_list_users(): void
    {
        $this->actingAsApi($this->member())->getJson($this->url('users'))->assertStatus(403);
    }

    public function test_admin_lists_filters_and_paginates_users(): void
    {
        $this->member();
        $this->member();

        $this->actingAsApi($this->admin())
            ->getJson($this->url('users?role=user&per_page=1'))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.total', 2);
    }

    public function test_admin_creates_a_user_with_a_role(): void
    {
        $this->actingAsApi($this->admin())
            ->postJson($this->url('users'), [
                'name' => 'New Admin',
                'email' => 'new@example.com',
                'password' => 'secret123',
                'role' => 'admin',
            ])->assertCreated()->assertJsonPath('data.roles', ['admin']);
    }

    public function test_created_user_defaults_to_the_user_role(): void
    {
        $this->actingAsApi($this->admin())
            ->postJson($this->url('users'), [
                'name' => 'Plain',
                'email' => 'plain@example.com',
                'password' => 'secret123',
            ])->assertCreated()->assertJsonPath('data.roles', ['user']);
    }

    public function test_unknown_role_is_rejected(): void
    {
        $this->actingAsApi($this->admin())
            ->postJson($this->url('users'), [
                'name' => 'X',
                'email' => 'x@example.com',
                'password' => 'secret123',
                'role' => 'manager',
            ])->assertStatus(422)->assertJsonStructure(['errors' => ['role']]);
    }

    public function test_regular_user_cannot_create_users(): void
    {
        $this->actingAsApi($this->member())
            ->postJson($this->url('users'), [
                'name' => 'X',
                'email' => 'x@example.com',
                'password' => 'secret123',
            ])->assertStatus(403);
    }

    public function test_user_views_only_their_own_record(): void
    {
        $user = $this->member();
        $other = $this->member();

        $this->actingAsApi($user)->getJson($this->url("users/{$user->id}"))->assertOk();
        $this->actingAsApi($user)->getJson($this->url("users/{$other->id}"))->assertStatus(403);
        $this->actingAsApi($this->admin())->getJson($this->url("users/{$other->id}"))->assertOk();
    }

    public function test_user_updates_own_name_but_cannot_touch_privileged_fields(): void
    {
        $user = $this->member();

        $this->actingAsApi($user)
            ->putJson($this->url("users/{$user->id}"), ['name' => 'Renamed', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Renamed')
            ->assertJsonPath('data.is_active', true);
    }

    public function test_user_cannot_update_someone_else(): void
    {
        $user = $this->member();
        $other = $this->member();

        $this->actingAsApi($user)
            ->putJson($this->url("users/{$other->id}"), ['name' => 'Hacked'])
            ->assertStatus(403);
    }

    public function test_admin_updates_and_deactivates_another_user(): void
    {
        $user = $this->member();

        $this->actingAsApi($this->admin())
            ->putJson($this->url("users/{$user->id}"), ['name' => 'Changed', 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Changed')
            ->assertJsonPath('data.is_active', false);
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAsApi($admin)
            ->putJson($this->url("users/{$admin->id}"), ['is_active' => false])
            ->assertStatus(409);
    }

    public function test_admin_assigns_and_removes_a_role_and_user_cannot(): void
    {
        $user = $this->member();
        $admin = $this->role('admin');

        $this->actingAsApi($user)
            ->postJson($this->url("users/{$user->id}/roles"), ['role_id' => $admin->id])
            ->assertStatus(403);

        $this->actingAsApi($this->admin())
            ->postJson($this->url("users/{$user->id}/roles"), ['role_id' => $admin->id])
            ->assertOk()
            ->assertJsonPath('data.roles', ['admin', 'user']);

        $this->actingAsApi($this->admin())
            ->deleteJson($this->url("users/{$user->id}/roles/{$admin->id}"))
            ->assertOk()
            ->assertJsonPath('data.roles', ['user']);
    }

    public function test_assigning_a_duplicate_or_missing_role_fails(): void
    {
        $user = $this->member();

        $this->actingAsApi($this->admin())
            ->postJson($this->url("users/{$user->id}/roles"), ['role_id' => $this->role('user')->id])
            ->assertStatus(409);

        $this->actingAsApi($this->admin())
            ->postJson($this->url("users/{$user->id}/roles"), ['role_id' => 9999])
            ->assertStatus(422);

        $this->actingAsApi($this->admin())
            ->deleteJson($this->url("users/{$user->id}/roles/9999"))
            ->assertStatus(404)
            ->assertJsonPath('message', 'Role not found.');

        $this->actingAsApi($this->admin())
            ->deleteJson($this->url("users/{$user->id}/roles/{$this->role('admin')->id}"))
            ->assertStatus(409);
    }

    public function test_admin_cannot_change_own_roles(): void
    {
        $admin = $this->admin();

        $this->actingAsApi($admin)
            ->postJson($this->url("users/{$admin->id}/roles"), ['role_id' => $this->role('user')->id])
            ->assertStatus(403);

        $this->actingAsApi($admin)
            ->deleteJson($this->url("users/{$admin->id}/roles/{$this->role('admin')->id}"))
            ->assertStatus(403);
    }

    public function test_last_admin_cannot_lose_the_admin_role(): void
    {
        $second = $this->member();
        $second->assignRole('admin');

        $this->actingAsApi($second)
            ->deleteJson($this->url("users/{$this->admin()->id}/roles/{$this->role('admin')->id}"))
            ->assertOk();

        $this->actingAsApi($this->admin())
            ->deleteJson($this->url("users/{$second->id}/roles/{$this->role('admin')->id}"))
            ->assertStatus(403);
    }

    public function test_user_can_be_created_with_a_custom_role(): void
    {
        \Spatie\Permission\Models\Role::create(['name' => 'editor', 'guard_name' => 'api']);

        $this->actingAsApi($this->admin())
            ->postJson($this->url('users'), [
                'name' => 'Ed',
                'email' => 'ed@example.com',
                'password' => 'secret123',
                'role' => 'editor',
            ])->assertCreated()->assertJsonPath('data.roles', ['editor']);
    }

    public function test_admin_deletes_a_user_and_cannot_delete_self(): void
    {
        $user = $this->member();
        $admin = $this->admin();

        $this->actingAsApi($admin)->deleteJson($this->url("users/{$user->id}"))->assertNoContent();
        $this->assertNull(User::find($user->id));

        $this->actingAsApi($admin)->deleteJson($this->url("users/{$admin->id}"))->assertStatus(403);
    }

    public function test_regular_user_cannot_delete_or_unlock(): void
    {
        $user = $this->member();
        $other = $this->member();

        $this->actingAsApi($user)->deleteJson($this->url("users/{$other->id}"))->assertStatus(403);
        $this->actingAsApi($user)->postJson($this->url("users/{$other->id}/unlock"))->assertStatus(403);
    }

    public function test_admin_unlocks_a_locked_user(): void
    {
        $user = $this->member();
        $user->update(['failed_login_attempts' => 5, 'locked_until' => now()->addMinutes(10)]);

        $this->actingAsApi($this->admin())
            ->postJson($this->url("users/{$user->id}/unlock"))
            ->assertOk()
            ->assertJsonPath('data.is_locked', false);

        $this->assertSame(0, $user->fresh()->failed_login_attempts);
    }
}
