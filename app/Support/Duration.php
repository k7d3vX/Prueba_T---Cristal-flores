<?php

declare(strict_types=1);

namespace App\Support;

/** Tiempo trabajado en lenguaje de la dueña: "3 hr 34 min", "0 hrs 2 min", "0 hrs 0 min 30 seg". */
final class Duration
{
    public static function format(int $seconds): string
    {
        $seconds = max(0, $seconds);
        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        if ($hours > 0) {
            return "{$hours} hr {$minutes} min";
        }

        if ($minutes > 0) {
            return "0 hrs {$minutes} min";
        }

        return '0 hrs 0 min '.($seconds % 60).' seg';
    }
}
