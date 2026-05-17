<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

/**
 * {@see RefreshDatabase} para suites que solo son válidas con PostgreSQL (migraciones / tenant).
 *
 * - Si el driver no es `pgsql`, omite el caso **antes** de `migrate:fresh` (evita errores SQL en SQLite).
 * - Si es PostgreSQL pero la base no parece dedicada a tests, omite (evita borrar datos de desarrollo).
 */
trait RefreshDatabaseWithPgsqlSafety
{
    use RefreshDatabase {
        beforeRefreshingDatabase as laravelBeforeRefreshingDatabase;
    }

    protected function beforeRefreshingDatabase(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Esta suite requiere PostgreSQL (multi-schema tenant).');

            return;
        }

        TenantMigrateTestGuards::guardIfUnsafePgsql($this);

        $this->laravelBeforeRefreshingDatabase();
    }
}
