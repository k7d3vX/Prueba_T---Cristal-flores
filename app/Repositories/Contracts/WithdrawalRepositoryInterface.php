<?php

namespace App\Repositories\Contracts;

use App\Enums\WithdrawalCategory;
use App\Models\CashSession;
use App\Models\Withdrawal;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface WithdrawalRepositoryInterface
{
    public function create(CashSession $session, string $amount, string $reason, ?CarbonInterface $at = null, WithdrawalCategory $category = WithdrawalCategory::Other): Withdrawal;

    public function forSession(CashSession $session, bool $newestFirst = false): Collection;

    /** Los últimos `$limit` registros, el más nuevo primero (para «mis últimos registros» del cajero). */
    public function recentForSession(CashSession $session, int $limit): Collection;

    public function findOrFail(int|string $id): Withdrawal;

    /** @param  array<string, mixed>  $attributes */
    public function update(Withdrawal $movement, array $attributes): Withdrawal;

    /** Solicitudes de anulación del cajero que la dueña aún no resolvió (con su turno, caja y cajero). */
    public function pendingVoidRequests(): Collection;
}
