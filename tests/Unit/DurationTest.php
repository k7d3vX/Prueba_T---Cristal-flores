<?php

namespace Tests\Unit;

use App\Support\Duration;
use PHPUnit\Framework\TestCase;

class DurationTest extends TestCase
{
    public function test_formats_worked_time_like_the_owner_expects(): void
    {
        $this->assertSame('3 hr 34 min', Duration::format(3 * 3600 + 34 * 60));
        $this->assertSame('0 hrs 2 min', Duration::format(120));
        $this->assertSame('0 hrs 0 min 30 seg', Duration::format(30));
        $this->assertSame('0 hrs 0 min 0 seg', Duration::format(0));
        $this->assertSame('1 hr 0 min', Duration::format(3600));
        $this->assertSame('12 hr 0 min', Duration::format(12 * 3600));
        $this->assertSame('0 hrs 59 min', Duration::format(59 * 60 + 59)); // los segundos solo se muestran bajo 1 min
    }

    public function test_negative_seconds_are_treated_as_zero(): void
    {
        $this->assertSame('0 hrs 0 min 0 seg', Duration::format(-50));
    }
}
