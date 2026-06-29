<?php

use App\Database\Migrations\TenantMigration;
use App\Support\Fel\SunatSerieCodigo;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends TenantMigration
{
    public function up(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('fel_series')) {
                return;
            }

            if (! Schema::hasColumn('fel_series', 'sede_id')) {
                Schema::table('fel_series', function (Blueprint $table): void {
                    $table->uuid('sede_id')->nullable()->after('id');
                });
            }

            $this->dropLegacyUniqueIfExists();

            $this->backfillSedeIdFromSedes();

            $this->ensureSedeScopedUniqueAndIndex();
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('fel_series') || ! Schema::hasColumn('fel_series', 'sede_id')) {
                return;
            }

            $this->dropSedeScopedUniqueIfExists();

            Schema::table('fel_series', function (Blueprint $table): void {
                $table->dropColumn('sede_id');
            });

            if (! $this->legacyUniqueExists()) {
                Schema::table('fel_series', function (Blueprint $table): void {
                    $table->unique(['tipo_comprobante', 'serie']);
                });
            }
        });
    }

    private function dropLegacyUniqueIfExists(): void
    {
        if (! $this->legacyUniqueExists()) {
            return;
        }

        Schema::table('fel_series', function (Blueprint $table): void {
            $table->dropUnique(['tipo_comprobante', 'serie']);
        });
    }

    private function legacyUniqueExists(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $schema = config('tenant.migration_schema');
        if (! is_string($schema)) {
            return false;
        }

        return (bool) DB::selectOne(
            'SELECT 1 FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE n.nspname = ? AND t.relname = ? AND c.conname = ?',
            [$schema, 'fel_series', 'fel_series_tipo_comprobante_serie_unique'],
        );
    }

    private function ensureSedeScopedUniqueAndIndex(): void
    {
        if (! $this->sedeScopedUniqueExists()) {
            Schema::table('fel_series', function (Blueprint $table): void {
                $table->unique(['sede_id', 'tipo_comprobante', 'serie']);
            });
        }

        if (DB::getDriverName() === 'pgsql') {
            $schema = config('tenant.migration_schema');
            if (is_string($schema)) {
                $indexExists = (bool) DB::selectOne(
                    'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND indexname = ?',
                    [$schema, 'fel_series_sede_id_tipo_comprobante_activo_index'],
                );

                if (! $indexExists) {
                    Schema::table('fel_series', function (Blueprint $table): void {
                        $table->index(['sede_id', 'tipo_comprobante', 'activo']);
                    });
                }
            }
        }
    }

    private function dropSedeScopedUniqueIfExists(): void
    {
        if (! $this->sedeScopedUniqueExists()) {
            return;
        }

        Schema::table('fel_series', function (Blueprint $table): void {
            $table->dropUnique(['sede_id', 'tipo_comprobante', 'serie']);
        });

        if (DB::getDriverName() === 'pgsql') {
            $schema = config('tenant.migration_schema');
            if (is_string($schema)) {
                $indexExists = (bool) DB::selectOne(
                    'SELECT 1 FROM pg_indexes WHERE schemaname = ? AND indexname = ?',
                    [$schema, 'fel_series_sede_id_tipo_comprobante_activo_index'],
                );

                if ($indexExists) {
                    Schema::table('fel_series', function (Blueprint $table): void {
                        $table->dropIndex(['sede_id', 'tipo_comprobante', 'activo']);
                    });
                }
            }
        }
    }

    private function sedeScopedUniqueExists(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return false;
        }

        $schema = config('tenant.migration_schema');
        if (! is_string($schema)) {
            return false;
        }

        return (bool) DB::selectOne(
            'SELECT 1 FROM pg_constraint c
             JOIN pg_class t ON t.oid = c.conrelid
             JOIN pg_namespace n ON n.oid = t.relnamespace
             WHERE n.nspname = ? AND t.relname = ? AND c.conname = ?',
            [$schema, 'fel_series', 'fel_series_sede_id_tipo_comprobante_serie_unique'],
        );
    }

    private function backfillSedeIdFromSedes(): void
    {
        $schema = config('tenant.migration_schema');
        if (! is_string($schema)) {
            return;
        }

        $tenant = DB::table('tenants')->where('schema_name', $schema)->first(['id']);
        if ($tenant === null) {
            return;
        }

        $sedes = DB::table('sedes')
            ->where('tenant_id', $tenant->id)
            ->orderBy('codigo')
            ->get(['id', 'serie_factura', 'serie_boleta']);

        if ($sedes->isEmpty()) {
            return;
        }

        $now = now();

        foreach ($sedes as $sede) {
            $this->assignSerieFromSedeColumn((string) $sede->id, 1, $sede->serie_factura, $now);
            $this->assignSerieFromSedeColumn((string) $sede->id, 2, $sede->serie_boleta, $now);
        }

        $fallbackSedeId = (string) $sedes->first()->id;

        $orphans = DB::table('fel_series')->whereNull('sede_id')->get(['id', 'tipo_comprobante', 'serie']);

        foreach ($orphans as $row) {
            $targetSedeId = $this->resolveOrphanSedeId(
                $sedes,
                (int) $row->tipo_comprobante,
                (string) $row->serie,
                $fallbackSedeId,
            );

            if ($this->sedeAlreadyHasSerie($targetSedeId, (int) $row->tipo_comprobante, (string) $row->serie, (string) $row->id)) {
                continue;
            }

            DB::table('fel_series')->where('id', $row->id)->update([
                'sede_id' => $targetSedeId,
                'updated_at' => $now,
            ]);
        }

        $stillOrphans = DB::table('fel_series')->whereNull('sede_id')->get(['id', 'tipo_comprobante', 'serie']);
        foreach ($stillOrphans as $row) {
            if ($this->sedeAlreadyHasSerie($fallbackSedeId, (int) $row->tipo_comprobante, (string) $row->serie, (string) $row->id)) {
                continue;
            }

            DB::table('fel_series')->where('id', $row->id)->update([
                'sede_id' => $fallbackSedeId,
                'updated_at' => $now,
            ]);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, object>  $sedes
     */
    private function resolveOrphanSedeId(
        $sedes,
        int $tipoComprobante,
        string $serie,
        string $fallbackSedeId,
    ): string {
        $column = $tipoComprobante === 1 ? 'serie_factura' : 'serie_boleta';

        foreach ($sedes as $sede) {
            $codigo = SunatSerieCodigo::normalizar($sede->{$column} ?? null);
            if ($codigo === $serie) {
                return (string) $sede->id;
            }
        }

        return $fallbackSedeId;
    }

    private function assignSerieFromSedeColumn(
        string $sedeId,
        int $tipoComprobante,
        mixed $rawCodigo,
        \DateTimeInterface $now,
    ): void {
        $codigo = SunatSerieCodigo::normalizar($rawCodigo !== null ? (string) $rawCodigo : null);
        if ($codigo === null) {
            return;
        }

        $owned = DB::table('fel_series')
            ->where('sede_id', $sedeId)
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('serie', $codigo)
            ->exists();

        if ($owned) {
            return;
        }

        $legacy = DB::table('fel_series')
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('serie', $codigo)
            ->whereNull('sede_id')
            ->orderBy('created_at')
            ->first(['id']);

        if ($legacy !== null) {
            DB::table('fel_series')->where('id', $legacy->id)->update([
                'sede_id' => $sedeId,
                'activo' => true,
                'updated_at' => $now,
            ]);

            return;
        }

        $template = DB::table('fel_series')
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('serie', $codigo)
            ->whereNotNull('sede_id')
            ->orderBy('created_at')
            ->first(['ultimo_correlativo']);

        DB::table('fel_series')->insert([
            'id' => (string) Str::uuid(),
            'sede_id' => $sedeId,
            'tipo_comprobante' => $tipoComprobante,
            'serie' => $codigo,
            'ultimo_correlativo' => $template->ultimo_correlativo ?? 0,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }

    private function sedeAlreadyHasSerie(
        string $sedeId,
        int $tipoComprobante,
        string $serie,
        string $excludeId,
    ): bool {
        return DB::table('fel_series')
            ->where('sede_id', $sedeId)
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('serie', $serie)
            ->where('id', '!=', $excludeId)
            ->exists();
    }
};
