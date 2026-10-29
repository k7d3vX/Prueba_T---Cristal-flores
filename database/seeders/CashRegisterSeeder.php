<?php

namespace Database\Seeders;

use App\Models\CashRegister;
use Illuminate\Database\Seeder;

class CashRegisterSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Caja Principal', 'Caja 2'] as $name) {
            CashRegister::firstOrCreate(['name' => $name]);
        }
    }
}
