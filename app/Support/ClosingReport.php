<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

/** Resumen del cierre + movimientos, para la vista de la dueña. */
final readonly class ClosingReport
{
    public function __construct(
        public ClosingSummary $summary,
        public Collection $sales,
        public Collection $withdrawals,
    ) {}
}
