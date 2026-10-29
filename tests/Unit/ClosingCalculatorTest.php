<?php

namespace Tests\Unit;

use App\Support\ClosingCalculator;
use App\Support\Money;
use PHPUnit\Framework\TestCase;

class ClosingCalculatorTest extends TestCase
{
    public function test_test_day_numbers(): void
    {
        // fondo 300 · efectivo 2.150 · QR 900 · salidas 200 · contado 2.180 (en centavos)
        $s = ClosingCalculator::calculate(30000, 215000, 90000, 20000, 218000);

        $this->assertSame(305000, $s->totalSales);
        $this->assertSame(225000, $s->expectedCash);   // el QR no entra al cajón
        $this->assertSame(-7000, $s->difference);
        $this->assertSame('short', $s->verdict);
        $this->assertSame(117000, $s->naiveGap);        // la cuenta de la dueña
        $this->assertSame(7000, $s->realShortage);
    }

    public function test_balanced_and_over(): void
    {
        $this->assertSame('balanced', ClosingCalculator::calculate(30000, 100000, 0, 0, 130000)->verdict);
        $this->assertSame('over', ClosingCalculator::calculate(30000, 100000, 0, 0, 131000)->verdict);
    }

    public function test_without_count_there_is_no_difference(): void
    {
        $s = ClosingCalculator::calculate(30000, 100000, 50000, 0);

        $this->assertNull($s->difference);
        $this->assertNull($s->verdict);
        $this->assertSame([], $s->reconciliation());
    }

    public function test_reconciliation_adds_up_to_the_real_difference(): void
    {
        $rows = ClosingCalculator::calculate(30000, 215000, 90000, 20000, 218000)->reconciliation();

        $this->assertSame(117000 - 90000 - 20000, $rows[3][1]);
        $this->assertSame(array_sum(array_column(array_slice($rows, 0, 3), 1)), $rows[3][1]);
    }

    public function test_money_conversions(): void
    {
        $this->assertSame(123450, Money::toCents('1234.50'));
        $this->assertSame(45000, Money::toCents('450'));
        $this->assertSame(150, Money::toCents('1.5'));
        $this->assertSame('-70.00', Money::fromCents(-7000));
        $this->assertSame('Bs 2.250,00', Money::format(225000));
        $this->assertSame('Bs -70,00', Money::format(-7000));
    }

    public function test_owner_claim_uses_the_clients_own_words(): void
    {
        $s = ClosingCalculator::calculate(30000, 215000, 90000, 20000, 218000);

        $this->assertSame(
            'Vendí Bs 3.050, más los Bs 300 de cambio son Bs 3.350, y el cajero contó Bs 2.180. ¡Me faltan Bs 1.170!',
            $s->ownerClaim(),
        );
    }

    public function test_owner_claim_when_there_is_surplus_or_it_matches_or_is_not_counted(): void
    {
        $this->assertStringEndsWith('¡Me sobran Bs 650!', ClosingCalculator::calculate(30000, 215000, 90000, 20000, 400000)->ownerClaim());
        $this->assertStringEndsWith('¡Me cuadra!', ClosingCalculator::calculate(30000, 100000, 0, 0, 130000)->ownerClaim());
        $this->assertNull(ClosingCalculator::calculate(30000, 100000, 0, 0)->ownerClaim());
    }

    public function test_short_money_hides_zero_cents(): void
    {
        $this->assertSame('Bs 3.050', Money::formatShort(305000));
        $this->assertSame('Bs 1.500,50', Money::formatShort(150050));
        $this->assertSame('Bs -70', Money::formatShort(-7000));
    }
}
