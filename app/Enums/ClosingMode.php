<?php

namespace App\Enums;

enum ClosingMode: string
{
    case Normal = 'normal';
    case Blind = 'blind';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Caja normal',
            self::Blind => 'Caja ciega',
        };
    }
}
