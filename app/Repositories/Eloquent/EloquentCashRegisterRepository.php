<?php

namespace App\Repositories\Eloquent;

use App\Models\CashRegister;
use App\Repositories\Contracts\CashRegisterRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentCashRegisterRepository implements CashRegisterRepositoryInterface
{
    public function all(): Collection
    {
        return CashRegister::query()->orderBy('id')->get();
    }
}
