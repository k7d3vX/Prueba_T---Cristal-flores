<?php

namespace App\Repositories\Contracts;

use App\Models\CashRegister;
use Illuminate\Support\Collection;

interface CashRegisterRepositoryInterface
{
    /** Todas las cajas de la tienda, en el orden en que se crearon. */
    public function all(): Collection;

    public function findOrFail(int|string $id): CashRegister;

    /** Fondo habitual de la caja: con el que el cajero abre si la dueña no abrió el turno. */
    public function setDefaultFloat(int $id, string $amount): void;
}
