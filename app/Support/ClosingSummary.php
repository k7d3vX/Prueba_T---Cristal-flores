<?php

declare(strict_types=1);

namespace App\Support;

/** Resultado del cierre. Todos los montos en centavos. */
final readonly class ClosingSummary
{
    public function __construct(
        public int $openingFloat,
        public int $cashSales,
        public int $qrSales,
        public int $totalSales,
        public int $withdrawals,
        public int $expectedCash,
        public ?int $countedCash,
        public ?int $difference,
        public ?string $verdict,   // 'balanced' | 'short' | 'over' | null (aún sin contar)
        public int $naiveExpected, // cuenta ingenua: ventas totales + fondo
        public ?int $naiveGap,     // cuenta ingenua − contado
        public ?int $realShortage, // esperado − contado (negativo = sobrante)
    ) {}

    public function verdictLabel(): string
    {
        return match ($this->verdict) {
            'balanced' => 'Cuadra',
            'short' => 'Faltante',
            'over' => 'Sobrante',
            default => 'Sin contar',
        };
    }

    /**
     * La cuenta de la dueña, en sus palabras (spec §3):
     * "Vendí Bs 3.050, más los Bs 300 de cambio son Bs 3.350, y el cajero contó Bs 2.180. ¡Me faltan Bs 1.170!"
     */
    public function ownerClaim(): ?string
    {
        if ($this->countedCash === null) {
            return null;
        }

        $ending = match (true) {
            $this->naiveGap > 0 => '¡Me faltan '.Money::formatShort($this->naiveGap).'!',
            $this->naiveGap < 0 => '¡Me sobran '.Money::formatShort(-$this->naiveGap).'!',
            default => '¡Me cuadra!',
        };

        return sprintf(
            'Vendí %s, más los %s de cambio son %s, y el cajero contó %s. %s',
            Money::formatShort($this->totalSales),
            Money::formatShort($this->openingFloat),
            Money::formatShort($this->naiveExpected),
            Money::formatShort($this->countedCash),
            $ending,
        );
    }

    /**
     * Conciliación (spec §7): de la cuenta de la dueña al faltante real.
     * Cada fila: [descripción, monto en centavos].
     *
     * @return list<array{0: string, 1: int}>
     */
    public function reconciliation(): array
    {
        if ($this->countedCash === null) {
            return [];
        }

        return [
            ['Tu cuenta: ventas totales + fondo − efectivo contado', $this->naiveGap],
            ['Menos: ventas por QR (se cobran en el banco, nunca entran al cajón)', -$this->qrSales],
            ['Menos: salidas de caja (plata que sí salió, con su motivo)', -$this->withdrawals],
            ['Diferencia real (esperado − contado)', $this->realShortage],
        ];
    }
}
