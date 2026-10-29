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

    public function findOrFail(int|string $id): CashRegister
    {
        return CashRegister::query()->findOrFail($id);
    }

    public function setDefaultFloat(int $id, string $amount): void
    {
        CashRegister::query()->whereKey($id)->update(['default_float' => $amount]);
    }
}
