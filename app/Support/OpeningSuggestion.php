<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Qué proponer al abrir un turno en una caja (monto inicial secuencial):
 * el fondo con que empezó el turno anterior. `previous` solo se llena para la dueña,
 * porque trae cifras de ventas que un cajero de caja ciega no debe ver.
 */
final readonly class OpeningSuggestion
{
    public function __construct(
        public string $openingFloat,      // "300.00"
        public ?PreviousTurn $previous,
    ) {}
}
