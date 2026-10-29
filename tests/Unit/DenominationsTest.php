<?php

namespace Tests\Unit;

use App\Support\Denominations;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DenominationsTest extends TestCase
{
    public function test_total_is_quantity_times_value_in_cents(): void
    {
        // El conteo del día de prueba: 4×200 + 7×100 + 12×50 + 4×20 = 2.180
        $this->assertSame(218000, Denominations::totalCents(['b200' => 4, 'b100' => 7, 'b50' => 12, 'b20' => 4]));
        $this->assertSame(350, Denominations::totalCents(['c2' => 1, 'c1' => '1', 'c050' => 1]));
        $this->assertSame(0, Denominations::totalCents(['b200' => null, 'b100' => '']));
    }

    public function test_normalize_keeps_only_positive_quantities_in_sheet_order(): void
    {
        $this->assertSame(['b200' => 4, 'c050' => 3], Denominations::normalize(['c050' => '3', 'b100' => 0, 'b200' => 4]));
    }

    #[DataProvider('invalid')]
    public function test_invalid_counts_are_rejected(array $counts): void
    {
        $this->expectException(InvalidArgumentException::class);
        Denominations::totalCents($counts);
    }

    public static function invalid(): array
    {
        return [
            'negativa' => [['b200' => -1]],
            'decimal' => [['b200' => '1.5']],
            'texto' => [['b200' => 'abc']],
            'demasiado grande' => [['b200' => 10000]],
            'denominación inventada' => [['b500' => 1]],
        ];
    }
}
