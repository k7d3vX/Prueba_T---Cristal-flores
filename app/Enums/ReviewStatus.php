<?php

namespace App\Enums;

enum ReviewStatus: string
{
    case Pending = 'pending';
    case Reviewed = 'reviewed';
    case Flagged = 'flagged';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente de revisión',
            self::Reviewed => 'Revisado',
            self::Flagged => 'Observado',
        };
    }
}
