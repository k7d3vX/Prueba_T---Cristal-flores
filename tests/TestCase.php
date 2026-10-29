<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * AR-30: los tests hacen `migrate:fresh` (RefreshDatabase). Si alguna vez apuntaran a la BD
     * de desarrollo la borrarían: se aborta ANTES de tocar nada si no es `caja_test`.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $connection = config('database.default');
        $database = config("database.connections.{$connection}.database");

        if ($database !== 'caja_test') {
            throw new RuntimeException("Los tests solo corren contra la BD «caja_test» (esta app apunta a «{$database}»). Abortado para no borrar datos reales.");
        }
    }
}
