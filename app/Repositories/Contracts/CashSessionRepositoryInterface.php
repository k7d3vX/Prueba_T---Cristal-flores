<?php

namespace App\Repositories\Contracts;

use App\Models\CashSession;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface CashSessionRepositoryInterface
{
    public function findOrFail(int|string $id): CashSession;

    /** Lee la caja bloqueando la fila (dentro de una transacción). */
    public function lockForUpdate(int $id): CashSession;

    /** Último turno cerrado de esa caja (el "anterior" del siguiente que se abra). */
    public function lastClosedFor(int $registerId): ?CashSession;

    /** Último turno cerrado de ese cajero. */
    public function lastClosedForCashier(int $cashierId): ?CashSession;

    /** Turno activo (por confirmar o abierto) del cajero, si tiene uno. */
    public function findActiveForCashier(int $cashierId): ?CashSession;

    /** ¿Esa caja ya tiene un turno activo? (otras cajas pueden estar activas). */
    public function existsActiveForRegister(int $registerId): bool;

    /** Turnos activos (por confirmar o abiertos) con su caja y su cajero. */
    public function activeSessions(): Collection;

    /** @param  array<string, mixed>  $attributes */
    public function create(array $attributes): CashSession;

    /** Cajas visibles para el usuario: la dueña ve todas; el cajero, las suyas. */
    public function listFor(User $user): Collection;

    /** Turnos cerrados que la dueña aún no revisó (pendientes u observados), el más antiguo primero. */
    public function pendingReview(): Collection;

    /** Turnos cerrados desde una fecha (con caja y cajero), el más reciente primero. */
    public function closedSince(CarbonInterface $since): Collection;

    /**
     * Historial: turnos cerrados filtrados (cashier_id, cash_register_id, from, to, review), el más reciente primero.
     *
     * @param  array<string, mixed>  $filters
     */
    public function history(array $filters): Collection;

    /** @param  array<string, mixed>  $attributes */
    public function update(CashSession $session, array $attributes): CashSession;

    /** @param  array<string, mixed>  $snapshot */
    public function markClosed(CashSession $session, array $snapshot): CashSession;
}
