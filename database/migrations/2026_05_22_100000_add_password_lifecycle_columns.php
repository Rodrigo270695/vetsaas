<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Fase 2.6 — Recuperación de contraseña + cambio obligatorio.
 *
 * 1. `users.must_change_password`:
 *      Bandera para forzar a un usuario a establecer su contraseña en
 *      el primer login (o tras un reset administrativo). Lo usa el
 *      middleware `EnsurePasswordIsChanged` y la pantalla
 *      `/cuenta/cambiar-password`. Por defecto `false` para que las
 *      cuentas existentes no se rompan.
 *
 * 2. `password_reset_tokens.tenant_id`:
 *      Permite que el mismo email tenga un token de reset distinto en
 *      cada clínica (o en el panel central). Sin esta columna, dos
 *      `maria@gmail.com` (una superadmin + una recepcionista de
 *      Clínica X, por ejemplo) competirían por la misma fila y se
 *      sobreescribirían el token mutuamente.
 *
 *      El índice composite asegura que solo exista UN token vigente
 *      por (tenant_id, email): para central usamos `'__central__'` como
 *      marcador, igual que en `users_tenant_email_unique`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('must_change_password')
                ->default(false)
                ->after('is_active');
        });

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->uuid('tenant_id')->nullable()->after('email');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_pkey');
            DB::statement('CREATE UNIQUE INDEX password_reset_tokens_tenant_email_unique ON password_reset_tokens (COALESCE(tenant_id::text, \'__central__\'), lower(email))');
            DB::statement('ALTER TABLE password_reset_tokens ADD CONSTRAINT password_reset_tokens_tenant_fk FOREIGN KEY (tenant_id) REFERENCES tenants (id) ON DELETE CASCADE');
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE password_reset_tokens DROP CONSTRAINT IF EXISTS password_reset_tokens_tenant_fk');
            DB::statement('DROP INDEX IF EXISTS password_reset_tokens_tenant_email_unique');
            DB::statement('ALTER TABLE password_reset_tokens ADD CONSTRAINT password_reset_tokens_pkey PRIMARY KEY (email)');
        }

        Schema::table('password_reset_tokens', function (Blueprint $table): void {
            $table->dropColumn('tenant_id');
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('must_change_password');
        });
    }
};
