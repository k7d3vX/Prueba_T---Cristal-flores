<?php

namespace App\Repositories\Contracts;

use App\Models\User;
use Illuminate\Support\Collection;

interface UserRepositoryInterface
{
    /** Usuarios con rol cajero, ordenados por nombre. */
    public function cashiers(): Collection;

    public function findByEmailOrFail(string $email): User;
}
