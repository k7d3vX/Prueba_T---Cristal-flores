<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Etiqueta del veredicto con tolerancia (RN-29). Clase pura: SOLO elige el texto; la diferencia
 * exacta nunca se modifica (AR-28). Montos en centavos.
 */
final class VerdictLabel
{
    /** @return array{tone: string, icon: string, text: string} */
    public static function for(?int $difference, int $tolerance): array
    {
        if ($difference === null) {
            return ['tone' => 'warn', 'icon' => '▲', 'text' => 'Sin contar'];
        }

        $amount = Money::format(abs($difference));

        return match (true) {
            $difference === 0 => ['tone' => 'ok', 'icon' => '✔', 'text' => 'La caja cuadra'],
            abs($difference) <= $tolerance => ['tone' => 'ok', 'icon' => '✔', 'text' => "Cuadra (diferencia de {$amount}, dentro de la tolerancia)"],
            $difference < 0 => ['tone' => 'bad', 'icon' => '▼', 'text' => "Faltante de {$amount}"],
            default => ['tone' => 'warn', 'icon' => '▲', 'text' => "Sobrante de {$amount}"],
        };
    }

    /** Versión corta para tablas: «Faltante Bs 70,00» / «Cuadra (Bs -3,00)». */
    public static function compact(?int $difference, int $tolerance): string
    {
        if ($difference === null) {
            return 'Sin contar';
        }

        $label = self::for($difference, $tolerance);

        return $label['icon'].' '.match (true) {
            $difference === 0 => 'Cuadra',
            abs($difference) <= $tolerance => 'Cuadra ('.Money::format($difference).')',
            $difference < 0 => 'Faltante '.Money::format(abs($difference)),
            default => 'Sobrante '.Money::format($difference),
        };
    }
}
