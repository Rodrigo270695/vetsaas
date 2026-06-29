<?php

namespace App\Console\Commands;

use App\Support\Geo\MojibakeFixer;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Corrige tildes y eñes del catálogo geográfico (país, departamentos, provincias, distritos).
 */
class GeoFixEncodingCommand extends Command
{
    protected $signature = 'vetsaas:geo-fix-encoding
                            {--dry-run : Muestra cambios sin escribir en la base de datos}
                            {--sync-denormalized : Actualiza distrito/provincia/departamento en sedes, tenants y propietarios}';

    protected $description = 'Repara tildes y eñes corruptas en el catálogo geográfico (ubigeo)';

    /** @var list<array{table: string, column: string}> */
    private const CATALOG_TABLES = [
        ['table' => 'paises', 'column' => 'name'],
        ['table' => 'departamentos', 'column' => 'name'],
        ['table' => 'provincias', 'column' => 'name'],
        ['table' => 'distritos', 'column' => 'name'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        if ($dryRun) {
            $this->warn('Modo simulación (--dry-run): no se guardará nada.');
        }

        $totalFixed = 0;

        foreach (self::CATALOG_TABLES as $spec) {
            $table = $spec['table'];
            $column = $spec['column'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                $this->line("  Omitido: no existe {$table}.{$column}");

                continue;
            }

            $fixed = $this->fixTable($table, $column, $dryRun);
            $totalFixed += $fixed;
            $this->info("  {$table}: {$fixed} fila(s) corregida(s)");
        }

        if ($totalFixed === 0) {
            $this->info('No se encontraron nombres con codificación corrupta.');
        } else {
            $this->newLine();
            $this->info("Total corregido: {$totalFixed} fila(s).");
        }

        if ($this->option('sync-denormalized') && ! $dryRun && $totalFixed > 0) {
            $this->syncDenormalizedGeoFields();
        }

        return self::SUCCESS;
    }

    private function fixTable(string $table, string $column, bool $dryRun): int
    {
        $driver = DB::getDriverName();
        $fixed = 0;

        if ($driver === 'pgsql') {
            $rows = DB::table($table)
                ->select(['id', $column])
                ->where(function ($query) use ($column): void {
                    $query->where($column, 'ILIKE', '%Ã%')
                        ->orWhere($column, 'ILIKE', '%Â%');
                })
                ->get();

            foreach ($rows as $row) {
                $original = (string) $row->{$column};
                $repaired = MojibakeFixer::repair($original);

                if ($repaired === $original) {
                    continue;
                }

                $this->line("    [{$row->id}] {$original} → {$repaired}");

                if (! $dryRun) {
                    DB::table($table)->where('id', $row->id)->update([$column => $repaired]);
                }

                $fixed++;
            }

            return $fixed;
        }

        $rows = DB::table($table)->select(['id', $column])->get();

        foreach ($rows as $row) {
            $original = (string) $row->{$column};
            if (! MojibakeFixer::looksCorrupted($original)) {
                continue;
            }

            $repaired = MojibakeFixer::repair($original);
            if ($repaired === $original) {
                continue;
            }

            $this->line("    [{$row->id}] {$original} → {$repaired}");

            if (! $dryRun) {
                DB::table($table)->where('id', $row->id)->update([$column => $repaired]);
            }

            $fixed++;
        }

        return $fixed;
    }

    private function syncDenormalizedGeoFields(): void
    {
        $this->newLine();
        $this->info('Sincronizando campos denormalizados desde distrito_id…');

        $updated = 0;

        if (Schema::hasTable('sedes') && Schema::hasColumn('sedes', 'distrito_id')) {
            $updated += $this->syncTableFromDistrito('sedes');
        }

        if (Schema::hasTable('tenants') && Schema::hasColumn('tenants', 'distrito_id')) {
            $updated += $this->syncTableFromDistrito('tenants');
        }

        if (Schema::hasTable('propietarios') && Schema::hasColumn('propietarios', 'distrito_id')) {
            $updated += $this->syncTableFromDistrito('propietarios');
        }

        $this->info("  Registros con ubicación actualizada: {$updated}");
    }

    private function syncTableFromDistrito(string $table): int
    {
        $rows = DB::table($table)
            ->whereNotNull('distrito_id')
            ->get(['id', 'distrito_id']);

        $count = 0;

        foreach ($rows as $row) {
            $geo = DB::table('distritos as d')
                ->join('provincias as p', 'p.id', '=', 'd.provincia_id')
                ->join('departamentos as dep', 'dep.id', '=', 'p.departamento_id')
                ->where('d.id', $row->distrito_id)
                ->first([
                    'd.name as distrito',
                    'p.name as provincia',
                    'dep.name as departamento',
                ]);

            if ($geo === null) {
                continue;
            }

            DB::table($table)->where('id', $row->id)->update([
                'distrito' => $geo->distrito,
                'provincia' => $geo->provincia,
                'departamento' => $geo->departamento,
            ]);

            $count++;
        }

        return $count;
    }
}
