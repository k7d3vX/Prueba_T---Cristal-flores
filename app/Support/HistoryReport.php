<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

final readonly class HistoryReport
{
    /**
     * @param  Collection<int, \App\Models\CashSession>  $turns
     * @param  Collection<int, array{name: string, turns: int, total: int, average: int, recent_shortages: int}>  $perCashier
     */
    public function __construct(
        public Collection $turns,
        public Collection $perCashier,
        public int $tolerance,
        public Collection $cashiers,
        public Collection $registers,
    ) {}
}
