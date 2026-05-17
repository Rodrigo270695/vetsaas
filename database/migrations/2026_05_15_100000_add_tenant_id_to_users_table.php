<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vincula cada usuario a la clínica (tenant) a la que pertenece.
 *
 * Diseño "single-login + datos aislados":
 *   - `tenant_id = NULL` → superadmin global del SaaS (panel central).
 *   - `tenant_id = <uuid>` → empleado de esa clínica.
 *
 * El mismo modelo `App\Models\User` autentica a todos:
 *   - Si el host es `localhost` (panel central) → solo entran users con
 *     `tenant_id = null`.
 *   - Si el host es `mi-clinica.localhost` → solo entran users con
 *     `tenant_id` igual al uuid de `mi-clinica`.
 *
 * Esa validación host↔tenant_id la hace el middleware `MatchUserTenant`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->uuid('tenant_id')
                ->nullable()
                ->after('id');

            $table->index('tenant_id', 'idx_users_tenant');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ADD CONSTRAINT users_tenant_id_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
            // Un mismo email puede repetirse SOLO si está en clínicas
            // distintas. Dentro de la misma clínica (o como superadmin)
            // el email es único.
            // En PostgreSQL, dropear el UNIQUE INDEX requiere primero
            // soltar la CONSTRAINT que lo refleja (Laravel los crea
            // como CONSTRAINT, no como INDEX puro).
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_email_unique');
            DB::statement('DROP INDEX IF EXISTS users_email_unique');
            DB::statement('CREATE UNIQUE INDEX users_tenant_email_unique ON users (COALESCE(tenant_id::text, \'__central__\'), lower(email))');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS users_tenant_email_unique');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_tenant_id_fk');
            DB::statement('CREATE UNIQUE INDEX users_email_unique ON users (email)');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_tenant');
            $table->dropColumn('tenant_id');
        });
    }
};
