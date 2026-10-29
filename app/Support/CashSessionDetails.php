<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\CashSession;
use Illuminate\Support\Collection;

/**
 * Lo que un usuario PUEDE ver de una caja (RN-5).
 * En caja ciega, para el cajero: showTotals=false, summary=null, listas vacías y una
 * copia de la sesión SIN los snapshots (esperado, contado, diferencia).
 */
final readonly class CashSessionDetails
{
    public function __construct(
        public CashSession $session,
        public bool $showTotals,
        public ?ClosingSummary $summary,
        public Collection $sales,
        public Collection $withdrawals,
        public bool $canClose,
    ) {}
}
