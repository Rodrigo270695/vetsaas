<?php

declare(strict_types=1);

namespace App\Support\Database;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Utilidades para tablas globales en PostgreSQL schema `public`.
 *
 * Con `search_path` apuntando al schema del tenant, `Schema::hasTable()`
 * consulta solo `current_schema()` y devuelve false aunque la tabla exista
 * en `public` (p. ej. bot_ia_announcements).
 */
final class PublicSchema
{
    public static function hasTable(string $table): bool
    {
        $table = trim($table);

        if ($table === '') {
            return false;
        }

        if (str_contains($table, '.')) {
            return Schema::hasTable($table);
        }

        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable($table);
        }

        $row = DB::selectOne(
            'SELECT EXISTS (
                SELECT 1
                FROM information_schema.tables
                WHERE table_schema = ?
                  AND table_name = ?
            ) AS exists',
            ['public', $table],
        );

        return (bool) ($row->exists ?? false);
    }
}
