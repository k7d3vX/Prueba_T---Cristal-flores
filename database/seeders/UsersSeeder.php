<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'owner@tienda.test'],
            ['name' => 'Dueña', 'password' => 'password', 'role' => Role::Owner],
        );

        User::updateOrCreate(
            ['email' => 'cajero@tienda.test'],
            ['name' => 'Cajero', 'password' => 'password', 'role' => Role::Cashier],
        );
    }
}
