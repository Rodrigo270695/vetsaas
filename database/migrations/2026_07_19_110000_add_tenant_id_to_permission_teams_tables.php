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
            }
        });

        if (! $this->indexExists('roles_tenant_id_index')) {
            Schema::table($rolesTable, function (Blueprint $table) use ($teamKey): void {
                $table->index($teamKey, 'roles_tenant_id_index');
            });
        }

        // En PostgreSQL el UNIQUE es un CONSTRAINT (el índice depende de él).
        // Hay que dropear la constraint, no el índice primero.
        $this->dropUniqueConstraintIfExists($rolesTable, 'roles_name_guard_name_unique');
        $this->dropUniqueConstraintIfExists($rolesTable, $rolesTable.'_name_guard_name_unique');

        if (! $this->constraintExists('roles_tenant_name_guard_unique')) {
            Schema::table($rolesTable, function (Blueprint $table) use ($teamKey): void {
                $table->unique([$teamKey, 'name', 'guard_name'], 'roles_tenant_name_guard_unique');
            });
        }

        Schema::table($modelHasRoles, function (Blueprint $table) use ($teamKey, $modelHasRoles): void {
            if (! Schema::hasColumn($modelHasRoles, $teamKey)) {
                $table->uuid($teamKey)->nullable()->after('role_id');
            }
        });
        if (! $this->indexExists('model_has_roles_tenant_id_index')) {
            Schema::table($modelHasRoles, function (Blueprint $table) use ($teamKey): void {
                $table->index($teamKey, 'model_has_roles_tenant_id_index');
            });
        }

        Schema::table($modelHasPermissions, function (Blueprint $table) use ($teamKey, $modelHasPermissions): void {
            if (! Schema::hasColumn($modelHasPermissions, $teamKey)) {
                $table->uuid($teamKey)->nullable()->after('permission_id');
            }
        });
        if (! $this->indexExists('model_has_permissions_tenant_id_index')) {
            Schema::table($modelHasPermissions, function (Blueprint $table) use ($teamKey): void {
                $table->index($teamKey, 'model_has_permissions_tenant_id_index');
            });
        }

        // La copia de roles por tenant NO va aquí: mantener el ALTER corto
        // evita locks largos con php-fpm activo. Correr después:
        //   php artisan vetsaas:migrate-roles-to-teams --force
    }

    public function down(): void
    {
        $rolesTable = config('permission.table_names.roles', 'roles');
        $modelHasRoles = config('permission.table_names.model_has_roles', 'model_has_roles');
        $modelHasPermissions = config('permission.table_names.model_has_permissions', 'model_has_permissions');
        $teamKey = config('permission.column_names.team_foreign_key', 'tenant_id');

        $this->dropUniqueConstraintIfExists($rolesTable, 'roles_tenant_name_guard_unique');

        Schema::table($rolesTable, function (Blueprint $table) use ($teamKey, $rolesTable): void {
            if ($this->indexExists('roles_tenant_id_index')) {
                $table->dropIndex('roles_tenant_id_index');
            }
            if (Schema::hasColumn($rolesTable, $teamKey)) {
                $table->dropColumn($teamKey);
            }
            $table->unique(['name', 'guard_name']);
        });

        Schema::table($modelHasRoles, function (Blueprint $table) use ($teamKey, $modelHasRoles): void {
            if ($this->indexExists('model_has_roles_tenant_id_index')) {
                $table->dropIndex('model_has_roles_tenant_id_index');
            }
            if (Schema::hasColumn($modelHasRoles, $teamKey)) {
                $table->dropColumn($teamKey);
            }
        });

        Schema::table($modelHasPermissions, function (Blueprint $table) use ($teamKey, $modelHasPermissions): void {
            if ($this->indexExists('model_has_permissions_tenant_id_index')) {
                $table->dropIndex('model_has_permissions_tenant_id_index');
            }
            if (Schema::hasColumn($modelHasPermissions, $teamKey)) {
                $table->dropColumn($teamKey);
            }
        });
    }

    private function dropUniqueConstraintIfExists(string $table, string $name): void
    {
        if (DB::getDriverName() === 'pgsql') {
            // DROP CONSTRAINT también elimina el índice asociado.
            // No usar DROP INDEX primero: PG responde 2BP01.
            DB::statement(sprintf(
                'ALTER TABLE %s DROP CONSTRAINT IF EXISTS %s',
                $this->quoteIdent($table),
                $this->quoteIdent($name),
            ));

            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($name): void {
            try {
                $blueprint->dropUnique($name);
            } catch (\Throwable) {
                // ignore
            }
        });
    }

    private function constraintExists(string $name): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $row = DB::selectOne(
            'select 1 as ok from pg_constraint where conname = ? limit 1',
            [$name],
        );

        return $row !== null;
    }

    private function indexExists(string $name): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $row = DB::selectOne(
            'select 1 as ok from pg_indexes where indexname = ? limit 1',
            [$name],
        );

        return $row !== null;
    }

    private function quoteIdent(string $ident): string
    {
        return '"'.str_replace('"', '""', $ident).'"';
    }
};
