<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Agrega los campos operativos del módulo Usuarios al schema base de
 * `users` (que solo tenía name/email/password de la stack stock).
 *
 *   - phone           → contacto opcional (WhatsApp, llamadas).
 *   - is_active       → flag de habilitación (suspender sin eliminar).
 *   - created_by_id   → audit trail: quién dio de alta a este usuario.
 *   - last_login_at   → última vez que se autenticó (lo actualiza Fortify).
 *   - deleted_at      → soft-delete para que el "eliminar" desde el
 *                       módulo no rompa relaciones (Spatie, tenants, etc.).
 *
 * No tocamos las columnas existentes (`name`, `email`, …) para no romper
 * Fortify/Inertia que las usan tal cual.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Defensivo: si la migración se re-corre o las columnas ya
            // existen por algún otro flujo (split tenants), saltamos.
            if (! Schema::hasColumn('users', 'phone')) {
                $table->string('phone', 32)->nullable()->after('email');
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('password');
            }

            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('is_active');
            }

            if (! Schema::hasColumn('users', 'created_by_id')) {
                // FK self-referencing: el creador es otro usuario. ON DELETE SET NULL
                // para preservar histórico cuando se borra al "padre".
                $table->foreignUuid('created_by_id')
                    ->nullable()
                    ->after('last_login_at')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('users', 'deleted_at')) {
                $table->softDeletes();
            }
        });

        // Índices útiles para el listado (filtros + búsqueda).
        Schema::table('users', function (Blueprint $table) {
            if (! $this->indexExists('users', 'users_is_active_index')) {
                $table->index('is_active');
            }
            if (! $this->indexExists('users', 'users_created_by_id_index')) {
                $table->index('created_by_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if ($this->indexExists('users', 'users_is_active_index')) {
                $table->dropIndex(['is_active']);
            }
            if ($this->indexExists('users', 'users_created_by_id_index')) {
                $table->dropIndex(['created_by_id']);
            }

            if (Schema::hasColumn('users', 'created_by_id')) {
                $table->dropConstrainedForeignId('created_by_id');
            }
            if (Schema::hasColumn('users', 'deleted_at')) {
                $table->dropSoftDeletes();
            }
            if (Schema::hasColumn('users', 'last_login_at')) {
                $table->dropColumn('last_login_at');
            }
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropColumn('is_active');
            }
            if (Schema::hasColumn('users', 'phone')) {
                $table->dropColumn('phone');
            }
        });
    }

    /**
     * Chequeo defensivo de existencia de un índice por nombre.
     * Postgres no expone hasIndex() de Schema, así que vamos por
     * pg_indexes. Si por error corre sobre otro driver, devolvemos false
     * para no bloquear la migración (la creación es idempotente igual).
     */
    private function indexExists(string $table, string $index): bool
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'pgsql') {
            return false;
        }

        return Schema::getConnection()
            ->table('pg_indexes')
            ->where('tablename', $table)
            ->where('indexname', $index)
            ->exists();
    }
};
