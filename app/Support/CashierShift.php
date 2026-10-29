<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Todo lo que el cajero PUEDE ver de su turno (RN-16, AR-16). Nada más llega a su vista:
 * ni resumen, ni totales, ni la sesión con sus snapshots.
 *
 * state: 'none' (sin turno) | 'pending' (fondo por confirmar) | 'open' (operando)
 */
final readonly class CashierShift
{
    /**
     * @param  Collection<int, array{kind: string, id: int, requested: bool, at: CarbonInterface, label: string, cents: int}>  $recent  últimos 5 registros, sin sumas
     * @param  Collection<int, \App\Models\CashRegister>  $freeRegisters  solo se llena si no tiene turno
     */
    public function __construct(
        public string $state,
        public ?string $registerName = null,
        public ?string $openingFloat = null,   // el fondo que recibió: es lo que tiene en la mano
        public bool $floatDisputed = false,
        public bool $canClose = false,
        public Collection $recent = new Collection,
        public Collection $freeRegisters = new Collection,
        public ?CarbonInterface $lastClosedAt = null,
        public bool $lastClosedBySelf = false,
    ) {}
}
