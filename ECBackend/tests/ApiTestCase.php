<?php

namespace Tests;

use App\Models\User;
use App\Support\AccessControl;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolesSeeder::class);
    }

    protected function admin(): User
    {
        return User::where('email', 'admin@example.com')->firstOrFail();
    }

    protected function member(): User
    {
        $user = User::factory()->create();
        $user->assignRole(AccessControl::USER_ROLE);

        return $user;
    }

    protected function role(string $name): Role
    {
        return Role::where('name', $name)->where('guard_name', 'api')->firstOrFail();
    }

    protected function actingAsApi(User $user): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withToken(JWTAuth::fromUser($user));
    }

    protected function url(string $path = ''): string
    {
        return '/api/v1/' . ltrim($path, '/');
    }
}
