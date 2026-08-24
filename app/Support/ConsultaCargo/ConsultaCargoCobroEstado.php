<?php

declare(strict_types=1);

namespace App\Support\ConsultaCargo;

use App\Models\ConsultaCargo;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * Estados de cobro visibles en listados (grooming, hotel, vacunas, historias…).
 *
 * - sin_precuenta: sin hoja de cargos pendiente ni venta vinculada
 * - precuenta_borrador: hay pre-cuenta editable
 * - precuenta_lista: confirmada, lista para cobrar en caja
 * - cobrado: ya hay venta (en el cargo histórico o en el registro padre)
 */
final class ConsultaCargoCobroEstado
{
    public const SIN_PRECUENTA = 'sin_precuenta';

    public const PRECUENTA_BORRADOR = 'precuenta_borrador';

    public const PRECUENTA_LISTA = 'precuenta_lista';

    public const COBRADO = 'cobrado';

    /** Filtros de listado (query string). */
    public const FILTER_TODOS = 'todos';

    public const FILTER_POR_COBRAR = 'por_cobrar';

    public const FILTER_COBRADO = 'cobrado';

    public const FILTER_SIN_PRECUENTA = 'sin_precuenta';

    public const FILTERS = [
        self::FILTER_TODOS,
        self::FILTER_POR_COBRAR,
        self::FILTER_COBRADO,
        self::FILTER_SIN_PRECUENTA,
    ];

    public static function resolve(
        ?ConsultaCargo $pending,
        int $cobradosCount,
        ?string $parentVentaId = null,
    ): string {
        if ($pending !== null) {
            return $pending->estado === ConsultaCargo::ESTADO_CONFIRMADO
                ? self::PRECUENTA_LISTA
                : self::PRECUENTA_BORRADOR;
        }

        if ($cobradosCount > 0 || ($parentVentaId !== null && $parentVentaId !== '')) {
            return self::COBRADO;
        }

        return self::SIN_PRECUENTA;
    }

    /**
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     * @param  non-empty-string  $cargosRelation  Nombre de hasMany hacia ConsultaCargo (p. ej. cargos)
     * @param  non-empty-string|null  $parentVentaColumn  Columna venta_id del padre si existe (con alias de tabla si hace falta)
     */
    public static function applyListFilter(
        Builder $query,
        string $filter,
        string $cargosRelation = 'cargos',
        ?string $parentVentaColumn = null,
        string $pendingRelation = 'cargo',
    ): void {
        if ($filter === self::FILTER_TODOS || ! in_array($filter, self::FILTERS, true)) {
            return;
        }

        if ($filter === self::FILTER_POR_COBRAR) {
            $query->whereHas($pendingRelation, function (Builder $q): void {
                $q->where('estado', ConsultaCargo::ESTADO_CONFIRMADO)
                    ->whereNull('venta_id');
            });

            return;
        }

        if ($filter === self::FILTER_COBRADO) {
            $query->where(function (Builder $q) use ($cargosRelation, $parentVentaColumn): void {
                if ($parentVentaColumn !== null) {
                    $q->whereNotNull($parentVentaColumn)
                        ->orWhereHas($cargosRelation, function (Builder $c): void {
                            $c->whereNotNull('venta_id');
                        });

                    return;
                }

                $q->whereHas($cargosRelation, function (Builder $c): void {
                    $c->whereNotNull('venta_id');
                });
            });

            return;
        }

        // sin_precuenta
        $query->whereDoesntHave($pendingRelation)
            ->whereDoesntHave($cargosRelation, function (Builder $c): void {
                $c->whereNotNull('venta_id');
            });

        if ($parentVentaColumn !== null) {
            $query->whereNull($parentVentaColumn);
        }
    }

    /**
     * withCount estándar para listados: cargos cobrados.
     *
     * @param  Builder<\Illuminate\Database\Eloquent\Model>  $query
     */
    public static function withCobradosCount(Builder $query, string $cargosRelation = 'cargos'): void
    {
        $query->withCount([
            "{$cargosRelation} as cargos_cobrados_count" => function (Builder|Relation $q): void {
                $q->whereNotNull('venta_id');
            },
        ]);
    }
}
