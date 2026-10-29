<?php

namespace App\Exceptions;

use DomainException;

/** La salida pedida supera el efectivo que hay en la caja. `available` va en centavos. */
class InsufficientCashException extends DomainException
{
    public function __construct(public readonly int $available)
    {
        parent::__construct('La salida supera el efectivo disponible en la caja.');
    }
}
