<?php

namespace App\Repositories\Contracts;

use Illuminate\Support\Collection;

interface CashRegisterRepositoryInterface
{
    /** Todas las cajas de la tienda, en el orden en que se crearon. */
    public function all(): Collection;
}
