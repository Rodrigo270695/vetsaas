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
        } finally {
            // Mantener el schema del tenant **delante** de `public` para que
            // el repositorio de migraciones de Laravel siga escribiendo en
            // `<tenant>.migrations` entre un `up()` y el siguiente. Si
            // volvemos solo a `public`, las filas acaban en `public.migrations`
            // y el resto de tenants cree que ya migró (Nothing to migrate).
            DB::statement('SET search_path TO "'.$safe.'", public');
        }
    }
}
