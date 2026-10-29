<?php

namespace Tests\Unit;

use App\Support\VerdictLabel;
use PHPUnit\Framework\TestCase;

class VerdictLabelTest extends TestCase
{
    private const TOL = 500; // Bs 5

    public function test_tolerance_only_changes_the_label(): void
    {
        $this->assertSame('La caja cuadra', VerdictLabel::for(0, self::TOL)['text']);
        $this->assertSame('Cuadra (diferencia de Bs 3,00, dentro de la tolerancia)', VerdictLabel::for(-300, self::TOL)['text']);
        $this->assertSame('Cuadra (diferencia de Bs 5,00, dentro de la tolerancia)', VerdictLabel::for(500, self::TOL)['text']); // el límite cuadra
        $this->assertSame('Faltante de Bs 5,01', VerdictLabel::for(-501, self::TOL)['text']);
        $this->assertSame('Sobrante de Bs 10,00', VerdictLabel::for(1000, self::TOL)['text']);
        $this->assertSame('Faltante de Bs 70,00', VerdictLabel::for(-7000, self::TOL)['text']);   // el día de prueba
    }

    public function test_tone_and_symbol_always_accompany_the_text(): void
    {
        $this->assertSame(['ok', '✔'], [VerdictLabel::for(-300, self::TOL)['tone'], VerdictLabel::for(-300, self::TOL)['icon']]);
        $this->assertSame(['bad', '▼'], [VerdictLabel::for(-700, self::TOL)['tone'], VerdictLabel::for(-700, self::TOL)['icon']]);
        $this->assertSame(['warn', '▲'], [VerdictLabel::for(700, self::TOL)['tone'], VerdictLabel::for(700, self::TOL)['icon']]);
    }

    public function test_compact_labels(): void
    {
        $this->assertSame('▼ Faltante Bs 70,00', VerdictLabel::compact(-7000, self::TOL));
        $this->assertSame('✔ Cuadra (Bs -3,00)', VerdictLabel::compact(-300, self::TOL));
        $this->assertSame('✔ Cuadra', VerdictLabel::compact(0, self::TOL));
        $this->assertSame('Sin contar', VerdictLabel::compact(null, self::TOL));
    }

    public function test_zero_tolerance_is_the_old_behaviour(): void
    {
        $this->assertSame('Faltante de Bs 0,01', VerdictLabel::for(-1, 0)['text']);
    }
}
