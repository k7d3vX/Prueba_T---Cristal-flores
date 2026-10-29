<?php

namespace App\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Cashier = 'cashier';
}
