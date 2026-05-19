<?php

namespace App\Database\Migrations;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

abstract class TenantMigration extends Migration
{
    protected function runInTenant(callable $callback): void
    {
        $schema = config('tenant.migration_schema');

        if (! is_string($schema) || ! preg_match('/^[a-z_][a-z0-9_]{0,62}$/i', $schema)) {
            return;
        }

        $safe = str_replace('"', '', $schema);

        DB::statement('SET search_path TO "'.$safe.'", public');

        try {
            $callback();
        } catch (\Throwable $e) {
            self::recoverPgsqlConnectionAfterFailure();

            throw $e;
        } finally {
            // Mantener el schema del tenant **delante** de `public` para que
            // el repositorio de migraciones de Laravel siga escribiendo en
            // `<tenant>.migrations` entre un `up()` y el siguiente. Si
            // volvemos solo a `public`, las filas acaban en `public.migrations`
            // y el resto de tenants cree que ya migró (Nothing to migrate).
            self::restoreTenantSearchPath($safe);
        }
    }

    /**
     * Tras un error en PostgreSQL la transacción queda abortada; cualquier
     * `SET search_path` posterior falla con SQLSTATE 25P02 y oculta la causa real.
     */
    protected static function recoverPgsqlConnectionAfterFailure(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        while (DB::transactionLevel() > 0) {
            DB::rollBack();
        }

        try {
            DB::statement('ROLLBACK');
        } catch (\Throwable) {
            // Sin transacción abierta en el servidor.
        }

        DB::reconnect();
    }

    protected static function restoreTenantSearchPath(string $safe): void
    {
        try {
            DB::statement('SET search_path TO "'.$safe.'", public');
        } catch (\Throwable) {
            self::recoverPgsqlConnectionAfterFailure();
            DB::statement('SET search_path TO "'.$safe.'", public');
        }
    }
}
