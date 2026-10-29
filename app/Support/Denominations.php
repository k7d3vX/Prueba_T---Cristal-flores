<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Billetes y monedas del conteo (RN-25). Claves sin punto para que funcionen como nombre de campo.
 * Valores en centavos enteros. Clase pura: el total lo calcula SIEMPRE el servidor.
 */
final class Denominations
{
    public const MAX_QUANTITY = 9999;

    /** clave => [etiqueta, centavos, tipo] */
    private const ALL = [
        'b200' => ['200', 20000, 'Billetes'],
        'b100' => ['100', 10000, 'Billetes'],
        'b50' => ['50', 5000, 'Billetes'],
        'b20' => ['20', 2000, 'Billetes'],
        'b10' => ['10', 1000, 'Billetes'],
        'c5' => ['5', 500, 'Monedas'],
        'c2' => ['2', 200, 'Monedas'],
        'c1' => ['1', 100, 'Monedas'],
        'c050' => ['0,50', 50, 'Monedas'],
    ];

    /** @return array<string, array{0: string, 1: int, 2: string}> */
    public static function all(): array
    {
        return self::ALL;
    }

    public static function isValid(string $key): bool
    {
        return isset(self::ALL[$key]);
    }

    /**
     * Total en centavos de un desglose {clave => cantidad}. Vacíos cuentan 0.
     *
     * @param  array<string, int|string|null>  $counts
     */
    public static function totalCents(array $counts): int
    {
        $total = 0;

        foreach ($counts as $key => $quantity) {
            if (! self::isValid((string) $key)) {
                throw new InvalidArgumentException("Denominación desconocida: {$key}");
            }

            $quantity = $quantity === null || $quantity === '' ? 0 : $quantity;

            if (! is_numeric($quantity) || (int) $quantity != $quantity || (int) $quantity < 0 || (int) $quantity > self::MAX_QUANTITY) {
                throw new InvalidArgumentException("Cantidad inválida para {$key}: {$quantity}");
            }

            $total += (int) $quantity * self::ALL[$key][1];
        }

        return $total;
    }

    /**
     * Solo las denominaciones con cantidad > 0, en el orden de la planilla.
     *
     * @param  array<string, int|string|null>  $counts
     * @return array<string, int>
     */
    public static function normalize(array $counts): array
    {
        self::totalCents($counts); // valida

        $clean = [];

        foreach (array_keys(self::ALL) as $key) {
            $quantity = (int) ($counts[$key] ?? 0);

            if ($quantity > 0) {
                $clean[$key] = $quantity;
            }
        }

        return $clean;
    }
}
