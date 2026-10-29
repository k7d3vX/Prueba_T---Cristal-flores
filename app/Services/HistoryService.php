<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\Contracts\CashRegisterRepositoryInterface;
use App\Repositories\Contracts\CashSessionRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Support\HistoryReport;
use App\Support\Money;
use Illuminate\Support\Collection;

/** Historial de la dueña (RN-31): turnos cerrados filtrados y resumen por cajero. */
class HistoryService
{
    public function __construct(
        private CashSessionRepositoryInterface $sessions,
        private CashRegisterRepositoryInterface $registers,
        private UserRepositoryInterface $users,
        private CashClosingService $closing,
    ) {}

    /** @param  array<string, mixed>  $filters */
    public function search(array $filters): HistoryReport
    {
        $turns = $this->sessions->history($filters);
        $tolerance = $this->closing->toleranceCents();

        $perCashier = $turns->groupBy('cashier_id')->map(function (Collection $own) use ($tolerance) {
            $diffs = $own->map(fn ($s) => Money::toCents($s->difference));

            return [
                'name' => $own->first()->cashier->name,
                'turns' => $own->count(),
                'total' => $diffs->sum(),
                'average' => intdiv($diffs->sum(), $own->count()),
                // el historial viene del más reciente al más antiguo: los 7 primeros son los últimos 7 turnos
                'recent_shortages' => $diffs->take(7)->filter(fn ($d) => $d < -$tolerance)->count(),
            ];
        })->sortBy('name')->values();

        return new HistoryReport(
            turns: $turns,
            perCashier: $perCashier,
            tolerance: $tolerance,
            cashiers: $this->users->cashiers(),
            registers: $this->registers->all(),
        );
    }
}
