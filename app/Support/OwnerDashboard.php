<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Collection;

/** Lo que muestra el panel de la dueña: cajas, turnos activos, quién está libre y el historial. */
final readonly class OwnerDashboard
{
    /**
     * @param  Collection<int, \App\Models\CashRegister>  $registers
     * @param  Collection<int, \App\Models\CashSession>  $active  turnos activos indexados por id de caja
     * @param  Collection<int, \App\Models\CashRegister>  $free  cajas sin turno activo
     * @param  Collection<int, \App\Models\User>  $freeCashiers  cajeros sin turno activo
     * @param  array<int, OpeningSuggestion>  $suggestions  por id de caja libre
     * @param  Collection<int, \App\Models\CashSession>  $sessions  historial de turnos
     * @param  Collection<int, \App\Models\CashSession>  $pendingReview  cierres por revisar (el más antiguo primero)
     * @param  Collection<int, array{kind: string, movement: \App\Models\Sale|\App\Models\Withdrawal}>  $voidRequests  anulaciones que pidió el cajero
     * @param  Collection<int, Alert>  $alerts  alertas de la dueña (RN-30)
     */
    public function __construct(
        public Collection $registers,
        public Collection $active,
        public Collection $free,
        public Collection $freeCashiers,
        public array $suggestions,
        public Collection $sessions,
        public Collection $pendingReview,
        public Collection $voidRequests,
        public Collection $alerts,
        public int $tolerance,
    ) {}
}
