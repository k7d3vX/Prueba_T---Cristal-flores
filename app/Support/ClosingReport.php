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

    /**
     * Línea de tiempo: ventas y salidas juntas, de la más antigua a la más nueva.
     *
     * @return Collection<int, array{kind: string, at: \Carbon\CarbonInterface, movement: \App\Models\Sale|\App\Models\Withdrawal}>
     */
    public function timeline(): Collection
    {
        $sales = $this->sales->map(fn ($m) => ['kind' => 'sale', 'at' => $m->created_at, 'movement' => $m]);
        $outs = $this->withdrawals->map(fn ($m) => ['kind' => 'withdrawal', 'at' => $m->created_at, 'movement' => $m]);

        return $sales->concat($outs)
            ->sortBy(fn ($r) => sprintf('%012d-%d-%09d', $r['at']->getTimestamp(), $r['kind'] === 'sale' ? 0 : 1, $r['movement']->id))
            ->values();
    }
}
