<?php

namespace App\Enums;

enum SessionStatus: string
{
    case PendingConfirmation = 'pending_confirmation';
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::PendingConfirmation => 'Por confirmar',
            self::Open => 'Abierta',
            self::Closed => 'Cerrada',
        };
    }
}
