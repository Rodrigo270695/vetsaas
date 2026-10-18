<?php

declare(strict_types=1);

namespace App\Support\Servicios;

use App\Models\ServicioClinico;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

/**
 * Búsqueda de servicios clínicos para autocompletados (exámenes auxiliares, etc.).
 */
final class ServicioClinicoCatalogoSearch
{
    /**
     * @return Collection<int, ServicioClinico>
     */
    public static function filas(string $q, bool $soloActivos = true, int $limit = 100): Collection
    {
        if (! Schema::hasTable('servicios_clinicos')) {
            return collect();
        }

        $query = ServicioClinico::query()->with('categoria:id,nombre');

        if ($soloActivos) {
            $query->where('activo', true);
        }

        self::aplicar($query, $q);

        return $query
            ->orderBy('orden')
            ->orderBy('nombre')
            ->limit(max(1, min($limit, 200)))
            ->get(['id', 'nombre']);
    }

    /**
     * @return list<array{id: string, nombre: string, categoria: string|null}>
     */
    public static function opciones(string $q, bool $soloActivos = true, int $limit = 100): array
    {
        if (trim($q) === '') {
            return [];
        }

        return self::filas($q, $soloActivos, $limit)
            ->map(static fn (ServicioClinico $row): array => [
                'id' => (string) $row->id,
                'nombre' => (string) $row->nombre,
                'categoria' => $row->categoria?->nombre !== null ? (string) $row->categoria->nombre : null,
            ])
            ->all();
    }

    public static function aplicar(Builder $query, string $q): void
    {
        $q = trim($q);
        if ($q === '') {
            return;
        }

        $like = '%'.addcslashes($q, '%_\\').'%';

        $query->where(function (Builder $inner) use ($like): void {
            $inner->where('nombre', 'ILIKE', $like)
                ->orWhereHas('categoria', function (Builder $cat) use ($like): void {
                    $cat->where('nombre', 'ILIKE', $like);
                });
        });
    }
}
