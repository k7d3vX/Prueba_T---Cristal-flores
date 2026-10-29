<?php

namespace App\Repositories\Contracts;

use App\Models\CashSession;
use App\Models\User;
use Illuminate\Support\Collection;

interface CashSessionRepositoryInterface
{
    public function findOrFail(int|string $id): CashSession;

    /** Lee la caja bloqueando la fila (dentro de una transacción). */
    public function lockForUpdate(int $id): CashSession;

    /** ¿Esa caja ya tiene una sesión abierta? (otras cajas pueden estar abiertas). */
    /** Último turno cerrado de esa caja (el "anterior" del siguiente que se abra). */
    public function lastClosedFor(int $registerId): ?CashSession;

    public function existsOpenForRegister(int $registerId): bool;

    /** Ids de las cajas que hoy tienen una sesión abierta. */
    public function openRegisterIds(): array;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): CashSession;

    /** Cajas visibles para el usuario: la dueña ve todas; el cajero, las suyas. */
    public function listFor(User $user): Collection;

    /** @param  array<string, mixed>  $snapshot */
    public function markClosed(CashSession $session, array $snapshot): CashSession;
}
