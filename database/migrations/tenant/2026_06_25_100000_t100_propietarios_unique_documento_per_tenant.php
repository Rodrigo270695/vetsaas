<?php

use App\Database\Migrations\TenantMigration;
use Illuminate\Support\Facades\DB;

return new class extends TenantMigration
{
    private const INDEX_NAME = 'propietarios_tipo_numero_documento_unique';

    public function up(): void
    {
        $this->runInTenant(function (): void {
            $exists = DB::selectOne(
                'SELECT 1 AS ok FROM pg_indexes WHERE schemaname = current_schema() AND indexname = ?',
                [self::INDEX_NAME],
            );

            if ($exists !== null) {
                return;
            }

            $duplicates = DB::select(
                <<<'SQL'
                SELECT COALESCE(UPPER(tipo_documento), '') AS tipo_key, numero_documento, COUNT(*) AS total
                FROM propietarios
                WHERE deleted_at IS NULL
                  AND numero_documento IS NOT NULL
                  AND btrim(numero_documento) <> ''
                GROUP BY 1, 2
                HAVING COUNT(*) > 1
                SQL
            );

            if ($duplicates !== []) {
                return;
            }

            DB::statement(
                'CREATE UNIQUE INDEX '.self::INDEX_NAME
                .' ON propietarios (COALESCE(UPPER(tipo_documento), \'\'), numero_documento)'
                .' WHERE numero_documento IS NOT NULL AND btrim(numero_documento) <> \'\' AND deleted_at IS NULL'
            );
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);
        });
    }
};
