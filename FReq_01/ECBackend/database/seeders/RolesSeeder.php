<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        foreach (AccessControl::ROLES as $name) {
            Role::firstOrCreate(['name' => $name, 'guard_name' => AccessControl::GUARD]);
        }

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            ['name' => 'Super Admin', 'password' => 'password']
        );

        $admin->syncRoles([AccessControl::ADMIN_ROLE]);
    }
}
