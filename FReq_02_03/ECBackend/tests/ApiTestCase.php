<?php

namespace Tests;

use App\Models\User;
use App\Support\AccessControl;
use Database\Seeders\RolesSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\Support\FakeInventoryGateway;
use App\Models\Product;
use App\Services\Inventory\InventoryGatewayInterface;
use Tymon\JWTAuth\Facades\JWTAuth;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeInventoryGateway $inventory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->inventory = new FakeInventoryGateway;
        $this->app->instance(InventoryGatewayInterface::class, $this->inventory);

        $this->seed(RolesSeeder::class);
    }

    protected function makeProduct(array $attributes = []): Product
    {
        $stock = $attributes['stock'] ?? 10;
        unset($attributes['stock']);

        $product = Product::factory()->create($attributes);
        $this->inventory->set($product->id, (int) $stock);

        return $product;
    }

    protected function stockOf(Product $product): int
    {
        return $this->inventory->stock($product->id);
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
        $this->app->forgetInstance('tymon.jwt');
        $this->app->forgetInstance('tymon.jwt.auth');

        return $this->withToken(JWTAuth::fromUser($user));
    }

    protected function url(string $path = ''): string
    {
        return '/api/v2/' . ltrim($path, '/');
    }
}
