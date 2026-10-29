<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Reglas del cierre (spec §4). Clase pura: sin BD ni framework, fácil de probar.
 *
 *   esperado   = fondo + ventas_efectivo − salidas      (RN-1; el QR no entra, RN-2)
 *   diferencia = contado − esperado                      (RN-3)
 */
final class ClosingCalculator
{
    public static function calculate(
        int $openingFloat,
        int $cashSales,
        int $qrSales,
        int $withdrawals,
        ?int $counted = null,
    ): ClosingSummary {
        $total = $cashSales + $qrSales;                              // RN-4
        $expected = $openingFloat + $cashSales - $withdrawals;       // RN-1
        $naiveExpected = $total + $openingFloat;

        $difference = $counted === null ? null : $counted - $expected; // RN-3
        $verdict = match (true) {
            $difference === null => null,
            $difference === 0 => 'balanced',
            $difference < 0 => 'short',
            default => 'over',
        };

        return new ClosingSummary(
            openingFloat: $openingFloat,
            cashSales: $cashSales,
            qrSales: $qrSales,
            totalSales: $total,
            withdrawals: $withdrawals,
            expectedCash: $expected,
            countedCash: $counted,
            difference: $difference,
            verdict: $verdict,
            naiveExpected: $naiveExpected,
            naiveGap: $counted === null ? null : $naiveExpected - $counted,
            realShortage: $counted === null ? null : $expected - $counted,
        );
    }
}
