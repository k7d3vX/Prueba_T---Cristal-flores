<?php

namespace App\Repositories\Eloquent;

use App\Enums\Role;
use App\Models\User;
use App\Repositories\Contracts\UserRepositoryInterface;
use Illuminate\Support\Collection;

class EloquentUserRepository implements UserRepositoryInterface
{
    public function cashiers(): Collection
    {
        return User::query()->where('role', Role::Cashier)->orderBy('name')->get();
    }

    public function findByEmailOrFail(string $email): User
    {
        return User::query()->where('email', $email)->firstOrFail();
    }
}
