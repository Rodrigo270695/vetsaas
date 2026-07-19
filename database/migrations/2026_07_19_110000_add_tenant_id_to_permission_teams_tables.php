<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Activa Spatie Teams con `tenant_id` (UUID) como team foreign key.
 * Roles de clínica quedan aislados por tenant; `superadmin` usa tenant_id null.
 */
return new class extends Migration
{
    public function up(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRoles = config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelHasPermissions = config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $teamKey = config('permission.column_names.team_foreign_key', 'tenant_id');

        Schema::table($rolesTable, function (Blueprint $table) use ($teamKey, $rolesTable): void {
            if (! Schema::hasColumn($rolesTable, $teamKey)) {
                $table->uuid($teamKey)->nullable()->after('id');
                $table->index($teamKey, 'roles_tenant_id_index');
            }
        });

        // Reemplazar unique (name, guard_name) por (tenant_id, name, guard_name).
        $this->dropUniqueIfExists($rolesTable, 'roles_name_guard_name_unique');
        $this->dropUniqueIfExists($rolesTable, $rolesTable.'_name_guard_name_unique');

        Schema::table($rolesTable, function (Blueprint $table) use ($teamKey): void {
            $table->unique([$teamKey, 'name', 'guard_name'], 'roles_tenant_name_guard_unique');
        });

        Schema::table($modelHasRoles, function (Blueprint $table) use ($teamKey, $modelHasRoles): void {
            if (! Schema::hasColumn($modelHasRoles, $teamKey)) {
                $table->uuid($teamKey)->nullable()->after('role_id');
                $table->index($teamKey, 'model_has_roles_tenant_id_index');
            }
        });

        Schema::table($modelHasPermissions, function (Blueprint $table) use ($teamKey, $modelHasPermissions): void {
            if (! Schema::hasColumn($modelHasPermissions, $teamKey)) {
                $table->uuid($teamKey)->nullable()->after('permission_id');
                $table->index($teamKey, 'model_has_permissions_tenant_id_index');
            }
        });

        // Migrar datos en el mismo paso para no dejar el sistema sin permisos
        // entre "migrate" y el comando artisan.
        if (DB::getDriverName() === 'pgsql' && config('permission.teams')) {
            try {
                $result = app(\App\Services\Rbac\MigrateRolesToTeams::class)->run(dryRun: false);
                logger()->info('migrate-roles-to-teams via migration', $result);
            } catch (\Throwable $e) {
                // En entornos sin tenants / seed incompleto no bloqueamos migrate.
                logger()->warning('migrate-roles-to-teams skipped: '.$e->getMessage());
            }
        }
    }

    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRoles = config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelHasPermissions = config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $teamKey = config('permission.column_names.team_foreign_key', 'tenant_id');

        $this->dropUniqueIfExists($rolesTable, 'roles_tenant_name_guard_unique');

        Schema::table($rolesTable, function (Blueprint $table) use ($teamKey, $rolesTable): void {
            if (Schema::hasColumn($rolesTable, $teamKey)) {
                $table->dropIndex('roles_tenant_id_index');
                $table->dropColumn($teamKey);
            }
            $table->unique(['name', 'guard_name']);
        });

        Schema::table($modelHasRoles, function (Blueprint $table) use ($teamKey, $modelHasRoles): void {
            if (Schema::hasColumn($modelHasRoles, $teamKey)) {
                $table->dropIndex('model_has_roles_tenant_id_index');
                $table->dropColumn($teamKey);
            }
        });

        Schema::table($modelHasPermissions, function (Blueprint $table) use ($teamKey, $modelHasPermissions): void {
            if (Schema::hasColumn($modelHasPermissions, $teamKey)) {
                $table->dropIndex('model_has_permissions_tenant_id_index');
                $table->dropColumn($teamKey);
            }
        });
    }

    private function dropUniqueIfExists(string $table, string $indexName): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement(sprintf('DROP INDEX IF EXISTS %s', $indexName));
            // En PG el unique constraint a veces se nombra igual que el índice.
            DB::statement(sprintf(
                'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
                $table,
                $indexName,
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($indexName): void {
            try {
                $blueprint->dropUnique($indexName);
            } catch (\Throwable) {
                // ignore
            }
        });
    }
};