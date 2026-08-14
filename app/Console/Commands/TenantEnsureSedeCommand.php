<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Distrito;
use App\Models\Sede;
use App\Models\Tenant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Crea una sede activa mínima para desbloquear clínicas sin sede
 * (middleware → /configuracion/sedes).
 */
final class TenantEnsureSedeCommand extends Command
{
    protected $signature = 'vetsaas:tenant-ensure-sede
        {slug : Slug del tenant}
        {--nombre= : Nombre de la sede (default: nombre comercial / razón social)}
        {--distrito-id= : ID de public.distritos (si se omite, intenta Lima/Lince)}';

    protected $description = 'Crea una sede activa con distrito si el tenant no tiene ninguna (desbloqueo)';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('Requiere PostgreSQL.');

            return self::FAILURE;
        }

        $slug = strtolower(trim((string) $this->argument('slug')));
        $tenant = Tenant::query()->where('slug', $slug)->first();

        if ($tenant === null) {
            $this->error("No existe tenant: {$slug}");

            return self::FAILURE;
        }

        $existing = Sede::query()
            ->where('tenant_id', $tenant->id)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->count();

        if ($existing > 0) {
            $withGeo = Sede::query()
                ->where('tenant_id', $tenant->id)
                ->where('activa', true)
                ->whereNull('deleted_at')
                ->whereNotNull('distrito_id')
                ->count();

            if ($withGeo > 0) {
                $this->info("Ya tiene {$existing} sede(s) activa(s) con ubicación. Nada que hacer.");

                return self::SUCCESS;
            }

            $this->warn("Tiene {$existing} sede(s) activa(s) pero sin distrito_id. Actualiza geo en la primera.");
            $sede = Sede::query()
                ->where('tenant_id', $tenant->id)
                ->where('activa', true)
                ->whereNull('deleted_at')
                ->orderBy('created_at')
                ->first();

            if ($sede === null) {
                $this->error('No se pudo cargar la sede.');

                return self::FAILURE;
            }

            return $this->applyDistrito($sede) ? self::SUCCESS : self::FAILURE;
        }

        $nombre = trim((string) ($this->option('nombre') ?: ''));
        if ($nombre === '') {
            $nombre = trim((string) ($tenant->nombre_comercial ?: $tenant->razon_social ?: 'Sede principal'));
        }

        try {
            $distrito = $this->resolveDistrito();
            if ($distrito === null) {
                $this->error('No se encontró un distrito. Pasa --distrito-id=ID');

                return self::FAILURE;
            }

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

            $this->info("Sede creada: {$sede->codigo} — {$sede->nombre}");
            $this->line("  distrito_id={$sede->distrito_id} ({$sede->departamento} / {$sede->provincia} / {$sede->distrito})");
            $this->line('  El cliente puede editar la ubicación real en Configuración › Sedes.');

            return self::SUCCESS;
        } catch (Throwable $e) {
            $this->error('Falló al crear sede: '.$e->getMessage());

            return self::FAILURE;
        }
    }

    private function applyDistrito(Sede $sede): bool
    {
        try {
            $distrito = $this->resolveDistrito();
            if ($distrito === null) {
                $this->error('No se encontró un distrito. Pasa --distrito-id=ID');

                return false;
            }

            $sede->forceFill([
                'distrito_id' => $distrito->id,
                'distrito' => $distrito->name,
                'provincia' => $distrito->provincia?->name,
                'departamento' => $distrito->provincia?->departamento?->name,
            ])->save();

            $this->info("Geo actualizada en {$sede->codigo}: {$sede->departamento} / {$sede->provincia} / {$sede->distrito}");

            return true;
        } catch (Throwable $e) {
            $this->error('Falló al actualizar geo: '.$e->getMessage());

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

        // Preferencia: Lince (Lima) → Miraflores → primer distrito activo de Lima.
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
