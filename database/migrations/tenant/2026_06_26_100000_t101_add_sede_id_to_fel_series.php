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

            Schema::table('fel_series', function (Blueprint $table): void {
                $table->uuid('sede_id')->nullable()->after('id');
            });

            $this->backfillSedeIdFromSedes();

            Schema::table('fel_series', function (Blueprint $table): void {
                $table->dropUnique(['tipo_comprobante', 'serie']);
                $table->unique(['sede_id', 'tipo_comprobante', 'serie']);
                $table->index(['sede_id', 'tipo_comprobante', 'activo']);
            });
        });
    }

    public function down(): void
    {
        $this->runInTenant(function (): void {
            if (! Schema::hasTable('fel_series') || ! Schema::hasColumn('fel_series', 'sede_id')) {
                return;
            }

            Schema::table('fel_series', function (Blueprint $table): void {
                $table->dropUnique(['sede_id', 'tipo_comprobante', 'serie']);
                $table->dropIndex(['sede_id', 'tipo_comprobante', 'activo']);
                $table->dropColumn('sede_id');
                $table->unique(['tipo_comprobante', 'serie']);
            });
        });
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

            $collision = DB::table('fel_series')
                ->where('sede_id', $targetSedeId)
                ->where('tipo_comprobante', $row->tipo_comprobante)
                ->where('serie', $row->serie)
                ->where('id', '!=', $row->id)
                ->exists();

            if ($collision) {
                continue;
            }

            DB::table('fel_series')->where('id', $row->id)->update([
                'sede_id' => $targetSedeId,
                'updated_at' => $now,
            ]);
        }

        $stillOrphans = DB::table('fel_series')->whereNull('sede_id')->get(['id', 'tipo_comprobante', 'serie']);
        foreach ($stillOrphans as $row) {
            $collision = DB::table('fel_series')
                ->where('sede_id', $fallbackSedeId)
                ->where('tipo_comprobante', $row->tipo_comprobante)
                ->where('serie', $row->serie)
                ->where('id', '!=', $row->id)
                ->exists();

            if ($collision) {
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

        $existing = DB::table('fel_series')
            ->where('tipo_comprobante', $tipoComprobante)
            ->where('serie', $codigo)
            ->where(function ($q) use ($sedeId): void {
                $q->whereNull('sede_id')->orWhere('sede_id', $sedeId);
            })
            ->orderByRaw('CASE WHEN sede_id IS NULL THEN 0 ELSE 1 END')
            ->first(['id', 'sede_id']);

        if ($existing !== null) {
            DB::table('fel_series')->where('id', $existing->id)->update([
                'sede_id' => $sedeId,
                'activo' => true,
                'updated_at' => $now,
            ]);

            return;
        }

        DB::table('fel_series')->insert([
            'id' => (string) Str::uuid(),
            'sede_id' => $sedeId,
            'tipo_comprobante' => $tipoComprobante,
            'serie' => $codigo,
            'ultimo_correlativo' => 0,
            'activo' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
};
