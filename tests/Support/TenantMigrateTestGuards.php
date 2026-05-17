<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Tenancy\TenantSchemaMigrator;
use Illuminate\Support\Facades\DB;

/**
 * Los tests que ejecutan `vetsaas:tenant-migrate` o `RefreshDatabase` contra PostgreSQL
 * pueden destruir datos o alterar `public.migrations` si usan la misma base que en
 * desarrollo (p. ej. `vetsaas`). Ver {@see TenantSchemaMigrator}.
 */
final class TenantMigrateTestGuards
{
    /**
     * Si la conexión por defecto es PostgreSQL y el nombre de la base no parece de test,
     * omite el caso (evita `migrate:fresh` y comandos tenant sobre datos reales).
     */
    public static function guardIfUnsafePgsql(object $case): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        if (self::pgsqlUsesDedicatedTestDatabase()) {
            return;
        }

        $case->markTestSkipped(self::message());
    }

    public static function pgsqlUsesDedicatedTestDatabase(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $name = strtolower((string) DB::connection()->getDatabaseName());
        if ($name === '') {
            return false;
        }

        if (str_ends_with($name, '_test') || str_ends_with($name, '_testing')) {
            return true;
        }

        return filter_var(env('VETSAAS_ALLOW_TENANT_MIGRATE_TESTS', false), FILTER_VALIDATE_BOOL);
    }

    public static function message(): string
    {
        $db = DB::connection()->getDatabaseName() ?? '(desconocida)';

        return 'Protección: estos tests ejecutan migraciones destructivas (`migrate:fresh` y/o '
            .'`vetsaas:tenant-migrate`) que no deben correr contra la base `'.$db.'`. '
            .'Usa una base dedicada cuyo nombre termine en `_test` o `_testing` (ej. `vetsaas_test`), '
            .'o define `VETSAAS_ALLOW_TENANT_MIGRATE_TESTS=true` en `.env` solo si aceptas ese riesgo.';
    }
}
