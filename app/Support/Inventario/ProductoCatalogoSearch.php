<?php

declare(strict_types=1);

namespace App\Support\Inventario;

use App\Models\Producto;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Busca en todo el catálogo. El tope es solo de filas que vuelven al combo,
 * no un corte alfabético del catálogo.
 */
final class ProductoCatalogoSearch
{
    /**
     * @param  list<string>  $columnas
     * @return Collection<int, Producto>
     */
    public static function filas(
        string $q,
        bool $soloActivos = false,
        bool $soloMedicamentos = false,
        array $columnas = ['id', 'nombre', 'sku'],
        int $limit = 100,
    ): Collection {
        $query = Producto::query();

        if ($soloActivos) {
            $query->where('activo', true);
        }

        if ($soloMedicamentos) {
            $query->where('medicamento', true);
        }

        self::aplicar($query, $q);

        return $query
            ->orderBy('nombre')
            ->limit(max(1, min($limit, 200)))
            ->get($columnas);
    }

    /**
     * @return list<array{id: string, nombre: string, sku: string|null}>
     */
    public static function opciones(
        string $q,
        bool $soloActivos = false,
        bool $soloMedicamentos = false,
        int $limit = 100,
    ): array {
        if (trim($q) === '') {
            return [];
        }

        return self::filas($q, $soloActivos, $soloMedicamentos, ['id', 'nombre', 'sku'], $limit)
            ->map(fn (Producto $producto): array => [
                'id' => (string) $producto->id,
                'nombre' => (string) $producto->nombre,
                'sku' => $producto->sku !== null ? (string) $producto->sku : null,
            ])
            ->all();
    }

    public static function aplicar(Builder $query, string $q, string $table = ''): void
    {
        $q = trim($q);
        if ($q === '') {
            return;
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $prefix = $table !== '' ? $table.'.' : '';

        $query->where(function (Builder $inner) use ($like, $prefix): void {
            $inner->where($prefix.'nombre', 'ILIKE', $like)
                ->orWhere($prefix.'sku', 'ILIKE', $like)
                ->orWhere($prefix.'codigo_barras', 'ILIKE', $like);
        });
    }
}
