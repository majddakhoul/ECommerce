<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\AccessControl;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesSeeder::class);

        $user = User::firstOrCreate(
            ['email' => 'user@example.com'],
            ['name' => 'Demo User', 'password' => 'password']
        );

        $user->syncRoles([AccessControl::USER_ROLE]);
    }
}
