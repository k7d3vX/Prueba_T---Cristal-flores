<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            UsersSeeder::class,
            CashRegisterSeeder::class,
            TestDaySeeder::class, // el día de prueba de la clienta (spec §7)
        ]);
    }
}
