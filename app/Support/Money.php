<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Dinero exacto: en BD es decimal(12,2) (string "1234.50");
 * en cálculos se usan CENTAVOS ENTEROS (123450). Nunca float.
 */
final class Money
{
    /** "1234.50" | "450" | 450 → 123450 */
    public static function toCents(string|int $amount): int
    {
        $amount = trim((string) $amount);

        if (! preg_match('/^(-)?(\d+)(?:\.(\d{1,2}))?$/', $amount, $m)) {
            throw new InvalidArgumentException("Monto inválido: {$amount}");
        }

        $cents = ((int) $m[2]) * 100 + (int) str_pad($m[3] ?? '0', 2, '0');

        return $m[1] === '-' ? -$cents : $cents;
    }

    /** 123450 → "1234.50" (formato de la BD) */
    public static function fromCents(int $cents): string
    {
        $abs = abs($cents);

        return ($cents < 0 ? '-' : '').intdiv($abs, 100).'.'.str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);
    }

    /** 123450 → "Bs 1.234,50" (formato para mostrar) */
    public static function format(int $cents): string
    {
        $abs = abs($cents);
        $entero = number_format(intdiv($abs, 100), 0, '', '.');
        $decimales = str_pad((string) ($abs % 100), 2, '0', STR_PAD_LEFT);

        return 'Bs '.($cents < 0 ? '-' : '').$entero.','.$decimales;
    }

    /** Como `format`, pero sin decimales cuando son ",00": 305000 → "Bs 3.050". */
    public static function formatShort(int $cents): string
    {
        if ($cents % 100 !== 0) {
            return self::format($cents);
        }

        return 'Bs '.($cents < 0 ? '-' : '').number_format(intdiv(abs($cents), 100), 0, '', '.');
    }
}
