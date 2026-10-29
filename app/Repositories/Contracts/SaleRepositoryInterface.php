<?php

namespace App\Repositories\Contracts;

use App\Enums\PaymentMethod;
use App\Models\CashSession;
use App\Models\Sale;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

interface SaleRepositoryInterface
{
    public function create(CashSession $session, string $amount, PaymentMethod $method, ?CarbonInterface $at = null): Sale;

    public function forSession(CashSession $session, bool $newestFirst = false): Collection;

    /** Los últimos `$limit` registros, el más nuevo primero (para «mis últimos registros» del cajero). */
    public function recentForSession(CashSession $session, int $limit): Collection;

    public function findOrFail(int|string $id): Sale;

    /** @param  array<string, mixed>  $attributes */
    public function update(Sale $movement, array $attributes): Sale;

    /** Solicitudes de anulación del cajero que la dueña aún no resolvió (con su turno, caja y cajero). */
    public function pendingVoidRequests(): Collection;
}
