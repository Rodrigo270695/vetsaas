<?php

namespace App\Support\Servicios;

use App\Grooming\GroomingCatalogoMode;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\ServicioClinico;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Búsqueda unificada de tarifas de servicios (clínicos + grooming)
 * para POS y pre-cuentas / cargos.
 */
final class ServicioTarifaSearch
{
    /**
     * @return list<array{nombre: string, precio_lista: string, origen: string, categoria: ?string}>
     */
    public static function search(string $q, int $limit = 30): array
    {
        $q = trim($q);
        $like = $q !== '' ? '%'.addcslashes($q, '%_\\').'%' : null;
        /** @var Collection<int, array{nombre: string, precio_lista: string, origen: string, categoria: ?string}> $results */
        $results = collect();

        if (Schema::hasTable('servicios_clinicos')) {
            $clinicaQuery = ServicioClinico::query()
                ->with('categoria:id,nombre')
                ->where('activo', true);

            if ($like !== null) {
                $clinicaQuery->where(function ($sub) use ($like): void {
                    $sub->where('nombre', 'ILIKE', $like)
                        ->orWhereHas('categoria', function ($cat) use ($like): void {
                            $cat->where('nombre', 'ILIKE', $like);
                        });
                });
            }

            $results = $results->concat(
                $clinicaQuery
                    ->orderBy('orden')
                    ->orderBy('nombre')
                    ->limit($limit)
                    ->get()
                    ->map(static fn (ServicioClinico $row): array => [
                        'nombre' => $row->nombre,
                        'precio_lista' => (string) $row->precio_lista,
                        'origen' => 'clinica',
                        'categoria' => $row->categoria?->nombre,
                    ]),
            );
        }

        $remaining = max(0, $limit - $results->count());

        if ($remaining > 0 && GroomingCatalogoMode::usaCatalogoPersonalizado()) {
            $query = GroomingServicio::query()->where('activo', true);

            if ($like !== null) {
                $query->where(function ($sub) use ($like): void {
                    $sub->where('nombre', 'ILIKE', $like)
                        ->orWhere('categoria', 'ILIKE', $like);
                });
            }

            $results = $results->concat(
                $query
                    ->orderBy('orden')
                    ->orderBy('nombre')
                    ->limit($remaining)
                    ->get(['nombre', 'precio_lista', 'categoria'])
                    ->map(static fn (GroomingServicio $row): array => [
                        'nombre' => $row->nombre,
                        'precio_lista' => (string) $row->precio_lista,
                        'origen' => 'grooming',
                        'categoria' => $row->categoria,
                    ]),
            );
        } elseif ($remaining > 0) {
            $query = GroomingServicioTarifa::query()->where('activo', true);

            if ($like !== null) {
                $query->where('servicio', 'ILIKE', $like);
            }

            $results = $results->concat(
                $query
                    ->orderBy('servicio')
                    ->limit($remaining)
                    ->get(['servicio', 'precio_lista'])
                    ->map(static fn (GroomingServicioTarifa $row): array => [
                        'nombre' => $row->servicio,
                        'precio_lista' => (string) $row->precio_lista,
                        'origen' => 'grooming',
                        'categoria' => null,
                    ]),
            );
        }

        return $results->values()->take($limit)->all();
    }
}
