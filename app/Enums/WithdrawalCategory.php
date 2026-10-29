<?php

namespace App\Enums;

enum WithdrawalCategory: string
{
    case Supplier = 'supplier';
    case Services = 'services';
    case Advance = 'advance';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'Proveedor',
            self::Services => 'Servicios',
            self::Advance => 'Adelanto',
            self::Other => 'Otro',
        };
    }
}
