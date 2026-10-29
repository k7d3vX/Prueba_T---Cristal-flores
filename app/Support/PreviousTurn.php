<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

/** Resumen del último turno cerrado de una caja (solo lo ve la dueña). Montos en centavos. */
final readonly class PreviousTurn
{
    public function __construct(
        public int $sessionId,
        public int $openingFloat,   // con cuánto se inició
        public int $earned,         // cuánto se ganó (ventas efectivo + QR)
        public int $total,          // inicio + ganado
        public CarbonInterface $openedAt,
        public CarbonInterface $closedAt,
        public int $workedSeconds,
    ) {}
}
