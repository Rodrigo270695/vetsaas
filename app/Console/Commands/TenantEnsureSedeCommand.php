<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Distrito;
use App\Models\Sede;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Desbloquea clínicas sin sede activa con distrito (middleware → /configuracion/sedes).
 *
 * Cuidado:
 * - Nunca pisa un distrito_id ya cargado.
 * - Con --all exige --dry-run o --apply (no escribe por accidente).
 * - Solo toca tenants vivos (trial/active/grace) salvo --incluir-suspendidos.
 */
final class TenantEnsureSedeCommand extends Command
{
    protected $signature = 'vetsaas:tenant-ensure-sede
        {slug? : Slug del tenant (omitir si usas --all)}
        {--all : Revisar todos los tenants vivos}
        {--dry-run : Solo listar qué haría (no escribe)}
        {--apply : Escribir cambios (requerido con --all; opcional con un slug)}
        {--incluir-suspendidos : Incluir tenants suspendidos en --all}
        {--nombre= : Nombre de la sede nueva (solo creación)}
        {--distrito-id= : ID de public.distritos (default: Lima/Lince)}';

    protected $description = 'Asegura sede activa con distrito (desbloqueo). Seguro: no pisa geo existente.';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Requiere PostgreSQL.');

            return self::FAILURE;
        }

        $all = (bool) $this->option('all');
        $dryRun = (bool) $this->option('dry-run');
        $apply = (bool) $this->option('apply');
        $slug = strtolower(trim((string) ($this->argument('slug') ?? '')));

        if ($all && $slug !== '') {
            $this->error('Usa slug O --all, no ambos.');

            return self::FAILURE;
        }

        if (! $all && $slug === '') {
            $this->error('Indica un slug o usa --all --dry-run / --all --apply.');

            return self::FAILURE;
        }

        if ($all && ! $dryRun && ! $apply) {
            $this->error('Con --all debes pasar --dry-run (solo ver) o --apply (escribir).');
            $this->line('Ejemplo: php artisan vetsaas:tenant-ensure-sede --all --dry-run');

            return self::FAILURE;
        }

        if ($dryRun && $apply) {
            $this->error('No combines --dry-run y --apply.');

            return self::FAILURE;
        }

        // Un slug sin flags: escribe (comportamiento histórico de desbloqueo puntual).
        $write = $all ? $apply : ($apply || ! $dryRun);
        if ($dryRun) {
            $write = false;
        }

        $distrito = $this->resolveDistrito();
        if ($distrito === null) {
            $this->error('No se encontró un distrito. Pasa --distrito-id=ID');

            return self::FAILURE;
        }

        $this->line(sprintf(
            'Distrito fallback: id=%d (%s / %s / %s)',
            $distrito->id,
            $distrito->provincia?->departamento?->name ?? '—',
            $distrito->provincia?->name ?? '—',
            $distrito->name,
        ));
        $this->line($write ? 'Modo: APLICAR cambios' : 'Modo: DRY-RUN (no escribe)');
        $this->newLine();

        $tenants = $all
            ? $this->tenantsForAll()
            : collect([Tenant::query()->where('slug', $slug)->first()])->filter();

        if ($tenants->isEmpty()) {
            $this->error($all ? 'No hay tenants para revisar.' : "No existe tenant: {$slug}");

            return self::FAILURE;
        }

        $created = 0;
        $geoFilled = 0;
        $ok = 0;
        $failed = 0;

        foreach ($tenants as $tenant) {
            /** @var Tenant $tenant */
            $result = $this->processTenant($tenant, $distrito, $write);
            match ($result) {
                'ok' => $ok++,
                'created' => $created++,
                'geo' => $geoFilled++,
                'failed' => $failed++,
                default => null,
            };
        }

        $this->newLine();
        $this->info(sprintf(
            'Resumen: ok=%d | crear_sede=%d | rellenar_geo=%d | fallos=%d | total=%d',
            $ok,
            $created,
            $geoFilled,
            $failed,
            $tenants->count(),
        ));

        if (! $write) {
            $this->comment('Nada se escribió. Para aplicar: añade --apply (o quita --dry-run en un slug).');
        }

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return Collection<int, Tenant>
     */
    private function tenantsForAll(): Collection
    {
        $estados = ['trial', 'active', 'grace'];
        if ($this->option('incluir-suspendidos')) {
            $estados[] = 'suspended';
        }

        return Tenant::query()
            ->whereIn('estado', $estados)
            ->whereNull('deleted_at')
            ->orderBy('slug')
            ->get(['id', 'slug', 'razon_social', 'nombre_comercial', 'telefono', 'email_admin', 'estado']);
    }

    /**
     * @return 'ok'|'created'|'geo'|'failed'
     */
    private function processTenant(Tenant $tenant, Distrito $distrito, bool $write): string
    {
        $label = "{$tenant->slug} [{$tenant->estado}]";

        $activas = Sede::query()
            ->where('tenant_id', $tenant->id)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('created_at')
            ->get();

        $conGeo = $activas->filter(fn (Sede $s) => $s->distrito_id !== null);
        $sinGeo = $activas->filter(fn (Sede $s) => $s->distrito_id === null);

        if ($activas->isNotEmpty() && $sinGeo->isEmpty()) {
            $this->line("✓ {$label}: {$activas->count()} sede(s) activa(s) con geo — nada");

            return 'ok';
        }

        if ($activas->isNotEmpty() && $sinGeo->isNotEmpty()) {
            $this->warn("○ {$label}: {$sinGeo->count()} sede(s) sin distrito_id (de {$activas->count()} activas)");
            if (! $write) {
                foreach ($sinGeo as $s) {
                    $this->line("    → rellenaría geo en {$s->codigo} — {$s->nombre}");
                }

                return 'geo';
            }

            $allOk = true;
            foreach ($sinGeo as $s) {
                if (! $this->applyDistrito($s, $distrito)) {
                    $allOk = false;
                }
            }

            return $allOk ? 'geo' : 'failed';
        }

        // Sin sedes activas
        $nombre = trim((string) ($this->option('nombre') ?: ''));
        if ($nombre === '') {
            $nombre = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: 'Sede principal'));
        }

        $this->warn("○ {$label}: sin sede activa — crearía «{$nombre}»");
        if (! $write) {
            return 'created';
        }

        try {
            $sede = Sede::query()->create([
                'tenant_id' => $tenant->id,
                'codigo' => Sede::generateNextCode((string) $tenant->id),
                'nombre' => mb_substr($nombre, 0, 150),
                'direccion' => null,
                'telefono' => $tenant->telefono,
                'email' => $tenant->email_admin,
                'distrito_id' => $distrito->id,
                'distrito' => $distrito->name,
                'provincia' => $distrito->provincia?->name,
                'departamento' => $distrito->provincia?->departamento?->name,
                'activa' => true,
            ]);

            $this->info("  Sede creada: {$sede->codigo} — {$sede->nombre}");
            $this->line("  distrito_id={$sede->distrito_id} (el cliente puede corregir ubicación real)");

            return 'created';
        } catch (Throwable $e) {
            $this->error("  Falló crear sede: {$e->getMessage()}");

            return 'failed';
        }
    }

    private function applyDistrito(Sede $sede, Distrito $distrito): bool
    {
        if ($sede->distrito_id !== null) {
            $this->line("  skip {$sede->codigo}: ya tiene distrito_id={$sede->distrito_id}");

            return true;
        }

        try {
            $sede->forceFill([
                'distrito_id' => $distrito->id,
                'distrito' => $distrito->name,
                'provincia' => $distrito->provincia?->name,
                'departamento' => $distrito->provincia?->departamento?->name,
            ])->save();

            $this->info("  Geo en {$sede->codigo}: {$sede->departamento} / {$sede->provincia} / {$sede->distrito}");

            return true;
        } catch (Throwable $e) {
            $this->error("  Falló geo {$sede->codigo}: {$e->getMessage()}");

            return false;
        }
    }

    private function resolveDistrito(): ?Distrito
    {
        $forced = (int) $this->option('distrito-id');
        if ($forced > 0) {
            return Distrito::query()
                ->with('provincia.departamento')
                ->find($forced);
        }

        $preferred = Distrito::query()
            ->with('provincia.departamento')
            ->where('status', true)
            ->where(function ($q): void {
                $q->where('name', 'ilike', 'Lince')
                    ->orWhere('name', 'ilike', 'Miraflores');
            })
            ->whereHas('provincia.departamento', function ($q): void {
                $q->where('name', 'ilike', 'Lima');
            })
            ->orderByRaw("CASE WHEN name ILIKE 'Lince' THEN 0 ELSE 1 END")
            ->first();

        if ($preferred !== null) {
            return $preferred;
        }

        return Distrito::query()
            ->with('provincia.departamento')
            ->where('status', true)
            ->whereHas('provincia.departamento', function ($q): void {
                $q->where('name', 'ilike', 'Lima');
            })
            ->orderBy('name')
            ->first();
    }
}
