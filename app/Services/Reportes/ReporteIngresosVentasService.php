<?php

declare(strict_types=1);

namespace App\Services\Reportes;

use App\Models\ClinicSetting;
use App\Models\FelSerie;
use App\Models\Venta;
use App\Models\VentaPago;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ingresos de ventas pagadas por tipo de comprobante (ticket / boleta / factura)
 * y método de pago, con rango de fechas (por defecto: esta semana).
 */
final class ReporteIngresosVentasService
{
    /** @var list<string> */
    public const TIPOS = ['ticket', 'boleta', 'factura'];

    /** @var list<string> */
    public const METODOS = VentaPago::METODOS;

    /**
     * @param  list<string>|null  $tipos
     * @param  list<string>|null  $metodos
     * @return array{
     *     moneda: string,
     *     filtros: array{
     *         fecha_desde: string,
     *         fecha_hasta: string,
     *         periodo: string,
     *         tipos: list<string>,
     *         metodos: list<string>
     *     },
     *     totales: array{ventas: int, ingresos: float},
     *     por_tipo: array<string, array{ventas: int, ingresos: float}>,
     *     por_metodo: array<string, array{ventas: int, ingresos: float}>,
     *     items: list<array<string, mixed>>
     * }
     */
    public function ingresos(
        ?string $fechaDesde,
        ?string $fechaHasta,
        ?string $periodo,
        ?array $tipos,
        ?array $metodos,
    ): array {
        [$periodoKey, $start, $end] = $this->resolveRange($fechaDesde, $fechaHasta, $periodo);
        $tiposSel = $this->normalizeTipos($tipos);
        $metodosSel = $this->normalizeMetodos($metodos);

        $empty = $this->emptyPayload($periodoKey, $start, $end, $tiposSel, $metodosSel);

        if (! Schema::hasTable('ventas')) {
            return $empty;
        }

        $q = DB::table('ventas as v')->whereNull('v.deleted_at');
        $this->applyPagadasEnRango($q, $start, $end);
        $this->applyTipoComprobanteFilter($q, $tiposSel);
        $this->applyMetodoPagoFilter($q, $metodosSel);

        if (Schema::hasTable('propietarios')) {
            $q->leftJoin('propietarios as pr', 'pr.id', '=', 'v.propietario_id');
        }
        if (Schema::hasTable('fel_documents')) {
            $q->leftJoin('fel_documents as fd', 'fd.id', '=', 'v.fel_document_id');
        }

        $select = [
            'v.id',
            'v.numero',
            'v.total',
            'v.metodo_pago',
            'v.fecha_pago',
            'v.created_at',
            'v.tipo_comprobante_sunat',
            'v.fel_estado',
        ];
        if (Schema::hasTable('propietarios')) {
            $select[] = 'pr.nombres as cliente_nombres';
            $select[] = 'pr.apellidos as cliente_apellidos';
        } else {
            $select[] = DB::raw('NULL as cliente_nombres');
            $select[] = DB::raw('NULL as cliente_apellidos');
        }
        if (Schema::hasTable('fel_documents')) {
            $select[] = 'fd.serie as fel_serie';
            $select[] = 'fd.correlativo as fel_correlativo';
        } else {
            $select[] = DB::raw('NULL as fel_serie');
            $select[] = DB::raw('NULL as fel_correlativo');
        }

        /** @var Collection<int, object> $rows */
        $rows = $q
            ->select($select)
            ->orderByDesc(DB::raw('COALESCE(v.fecha_pago, v.created_at)'))
            ->limit(3000)
            ->get();

        $pagosByVenta = $this->pagosPorVenta($rows->pluck('id')->all());

        $items = [];
        $ingresos = 0.0;
        $porTipo = $this->emptyBreakdown(self::TIPOS);
        $porMetodo = $this->emptyBreakdown(self::METODOS);

        foreach ($rows as $row) {
            $tipo = $this->tipoKey($row->tipo_comprobante_sunat ?? null);
            $total = round((float) $row->total, 2);
            $fecha = $row->fecha_pago ?? $row->created_at;
            $pagos = $pagosByVenta[(string) $row->id] ?? [];
            $metodosLinea = $this->metodosDeVenta((string) ($row->metodo_pago ?? ''), $pagos);

            $item = [
                'id' => (string) $row->id,
                'fecha' => $fecha !== null ? (string) $fecha : null,
                'numero' => (string) $row->numero,
                'comprobante' => $this->comprobanteLabel($tipo, $row),
                'tipo' => $tipo,
                'cliente' => $this->clienteNombre($row->cliente_nombres ?? null, $row->cliente_apellidos ?? null),
                'metodo_pago' => (string) ($row->metodo_pago ?? ''),
                'metodos' => $metodosLinea,
                'metodos_label' => $this->metodosLabel($metodosLinea, $pagos),
                'total' => $total,
                'fel_estado' => (string) ($row->fel_estado ?? ''),
            ];
            $items[] = $item;

            $ingresos += $total;
            $porTipo[$tipo]['ventas']++;
            $porTipo[$tipo]['ingresos'] = round($porTipo[$tipo]['ingresos'] + $total, 2);

            $this->accumulateMetodos($porMetodo, $metodosLinea, $pagos, $total, $metodosSel);
        }

        return [
            'moneda' => $this->resolveMoneda(),
            'filtros' => [
                'fecha_desde' => $start->toDateString(),
                'fecha_hasta' => $end->toDateString(),
                'periodo' => $periodoKey,
                'tipos' => $tiposSel,
                'metodos' => $metodosSel,
            ],
            'totales' => [
                'ventas' => count($items),
                'ingresos' => round($ingresos, 2),
            ],
            'por_tipo' => $porTipo,
            'por_metodo' => $porMetodo,
            'items' => $items,
        ];
    }

    /**
     * @param  list<string>|null  $raw
     * @return list<string>
     */
    public function normalizeTipos(?array $raw): array
    {
        return $this->normalizeList($raw, self::TIPOS);
    }

    /**
     * @param  list<string>|null  $raw
     * @return list<string>
     */
    public function normalizeMetodos(?array $raw): array
    {
        return $this->normalizeList($raw, self::METODOS);
    }

    /**
     * @param  list<string>|null  $raw
     * @param  list<string>  $allowed
     * @return list<string>
     */
    public function normalizeList(?array $raw, array $allowed): array
    {
        if ($raw === null || $raw === []) {
            return $allowed;
        }

        $out = [];
        foreach ($raw as $value) {
            $key = strtolower(trim((string) $value));
            if (in_array($key, $allowed, true)) {
                $out[] = $key;
            }
        }

        $out = array_values(array_unique($out));

        return $out === [] ? $allowed : $out;
    }

    /**
     * @return array{0: string, 1: CarbonInterface, 2: CarbonInterface}
     */
    public function resolveRange(?string $fechaDesde, ?string $fechaHasta, ?string $periodo): array
    {
        $tz = (string) config('app.timezone');
        $now = now($tz);

        if (is_string($fechaDesde) && is_string($fechaHasta)
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaDesde) === 1
            && preg_match('/^\d{4}-\d{2}-\d{2}$/', $fechaHasta) === 1
        ) {
            $start = $now->copy()->parse($fechaDesde)->startOfDay();
            $end = $now->copy()->parse($fechaHasta)->endOfDay();
            if ($start->greaterThan($end)) {
                [$start, $end] = [$end->copy()->startOfDay(), $start->copy()->endOfDay()];
            }

            $weekStart = $now->copy()->startOfWeek()->toDateString();
            $weekEnd = $now->copy()->endOfWeek()->toDateString();
            $periodoKey = ($start->toDateString() === $weekStart && $end->toDateString() === $weekEnd)
                ? 'semana'
                : 'personalizado';

            return [$periodoKey, $start, $end];
        }

        return match ($periodo) {
            'mes_actual' => [
                'mes_actual',
                $now->copy()->startOfMonth(),
                $now->copy()->endOfMonth(),
            ],
            'mes_pasado' => [
                'mes_pasado',
                $now->copy()->subMonth()->startOfMonth(),
                $now->copy()->subMonth()->endOfMonth(),
            ],
            default => [
                'semana',
                $now->copy()->startOfWeek(),
                $now->copy()->endOfWeek(),
            ],
        };
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $q
     */
    private function applyPagadasEnRango($q, CarbonInterface $start, CarbonInterface $end): void
    {
        $q->where('v.estado', Venta::ESTADO_PAGADO)
            ->where(function ($query) use ($start, $end): void {
                $query->whereBetween('v.fecha_pago', [$start, $end])
                    ->orWhere(function ($inner) use ($start, $end): void {
                        $inner->whereNull('v.fecha_pago')
                            ->whereBetween('v.created_at', [$start, $end]);
                    });
            })
            ->where(function ($query): void {
                $query->where(function ($ticket): void {
                    $ticket->where(function ($tipo): void {
                        $tipo->whereNull('v.tipo_comprobante_sunat')
                            ->orWhere('v.tipo_comprobante_sunat', FelSerie::TIPO_TICKET);
                    })->where('v.fel_estado', Venta::FEL_SIN_CPE);
                })->orWhere(function ($sunat): void {
                    $sunat->whereIn('v.tipo_comprobante_sunat', [
                        FelSerie::TIPO_FACTURA,
                        FelSerie::TIPO_BOLETA,
                    ])->where('v.fel_estado', Venta::FEL_EMITIDO);
                });
            });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $q
     * @param  list<string>  $tipos
     */
    private function applyTipoComprobanteFilter($q, array $tipos): void
    {
        $wantTicket = in_array('ticket', $tipos, true);
        $tiposSunat = [];
        if (in_array('factura', $tipos, true)) {
            $tiposSunat[] = FelSerie::TIPO_FACTURA;
        }
        if (in_array('boleta', $tipos, true)) {
            $tiposSunat[] = FelSerie::TIPO_BOLETA;
        }

        $q->where(function ($query) use ($wantTicket, $tiposSunat): void {
            $applied = false;
            if ($wantTicket) {
                $query->where(function ($ticket): void {
                    $ticket->whereNull('v.tipo_comprobante_sunat')
                        ->orWhere('v.tipo_comprobante_sunat', FelSerie::TIPO_TICKET);
                });
                $applied = true;
            }
            if ($tiposSunat !== []) {
                if ($applied) {
                    $query->orWhereIn('v.tipo_comprobante_sunat', $tiposSunat);
                } else {
                    $query->whereIn('v.tipo_comprobante_sunat', $tiposSunat);
                }
            }
            if (! $applied && $tiposSunat === []) {
                $query->whereRaw('1 = 0');
            }
        });
    }

    /**
     * @param  \Illuminate\Database\Query\Builder  $q
     * @param  list<string>  $metodos
     */
    private function applyMetodoPagoFilter($q, array $metodos): void
    {
        if (count($metodos) === count(self::METODOS)) {
            return;
        }

        $q->where(function ($query) use ($metodos): void {
            $query->whereIn('v.metodo_pago', $metodos);
            if (Schema::hasTable('venta_pagos')) {
                $query->orWhereExists(function ($ex) use ($metodos): void {
                    $ex->selectRaw('1')
                        ->from('venta_pagos as vp')
                        ->whereColumn('vp.venta_id', 'v.id')
                        ->whereIn('vp.metodo', $metodos);
                });
            }
        });
    }

    /**
     * @param  list<string>  $ventaIds
     * @return array<string, list<array{metodo: string, monto: float}>>
     */
    private function pagosPorVenta(array $ventaIds): array
    {
        if ($ventaIds === [] || ! Schema::hasTable('venta_pagos')) {
            return [];
        }

        $rows = DB::table('venta_pagos')
            ->whereIn('venta_id', $ventaIds)
            ->orderBy('orden')
            ->get(['venta_id', 'metodo', 'monto']);

        $map = [];
        foreach ($rows as $row) {
            $id = (string) $row->venta_id;
            $map[$id][] = [
                'metodo' => (string) $row->metodo,
                'monto' => round((float) $row->monto, 2),
            ];
        }

        return $map;
    }

    /**
     * @param  list<array{metodo: string, monto: float}>  $pagos
     * @return list<string>
     */
    private function metodosDeVenta(string $metodoPago, array $pagos): array
    {
        if ($pagos !== []) {
            $keys = [];
            foreach ($pagos as $pago) {
                if (in_array($pago['metodo'], self::METODOS, true)) {
                    $keys[] = $pago['metodo'];
                }
            }

            return array_values(array_unique($keys));
        }

        if (in_array($metodoPago, self::METODOS, true)) {
            return [$metodoPago];
        }

        return $metodoPago !== '' ? [$metodoPago] : [];
    }

    /**
     * @param  list<string>  $metodos
     * @param  list<array{metodo: string, monto: float}>  $pagos
     */
    private function metodosLabel(array $metodos, array $pagos): string
    {
        if ($pagos !== []) {
            $parts = [];
            foreach ($pagos as $pago) {
                $parts[] = $pago['metodo'].' '.number_format($pago['monto'], 2, '.', ',');
            }

            return implode(' · ', $parts);
        }

        if ($metodos === []) {
            return '—';
        }

        return implode(', ', $metodos);
    }

    /**
     * @param  array<string, array{ventas: int, ingresos: float}>  $porMetodo
     * @param  list<string>  $metodosLinea
     * @param  list<array{metodo: string, monto: float}>  $pagos
     * @param  list<string>  $metodosSel
     */
    private function accumulateMetodos(
        array &$porMetodo,
        array $metodosLinea,
        array $pagos,
        float $total,
        array $metodosSel,
    ): void {
        if ($pagos !== []) {
            foreach ($pagos as $pago) {
                $m = $pago['metodo'];
                if (! isset($porMetodo[$m])) {
                    continue;
                }
                if (! in_array($m, $metodosSel, true)) {
                    continue;
                }
                $porMetodo[$m]['ventas']++;
                $porMetodo[$m]['ingresos'] = round($porMetodo[$m]['ingresos'] + $pago['monto'], 2);
            }

            return;
        }

        foreach ($metodosLinea as $m) {
            if (! isset($porMetodo[$m]) || ! in_array($m, $metodosSel, true)) {
                continue;
            }
            $porMetodo[$m]['ventas']++;
            $porMetodo[$m]['ingresos'] = round($porMetodo[$m]['ingresos'] + $total, 2);
        }
    }

    public function tipoKey(mixed $tipoComprobante): string
    {
        return match ((int) ($tipoComprobante ?? FelSerie::TIPO_TICKET)) {
            FelSerie::TIPO_FACTURA => 'factura',
            FelSerie::TIPO_BOLETA => 'boleta',
            default => 'ticket',
        };
    }

    private function comprobanteLabel(string $tipo, object $row): string
    {
        $serie = trim((string) ($row->fel_serie ?? ''));
        $corr = $row->fel_correlativo ?? null;
        if ($serie !== '' && $corr !== null) {
            return $serie.'-'.str_pad((string) $corr, 8, '0', STR_PAD_LEFT);
        }

        return (string) ($row->numero ?? '');
    }

    private function clienteNombre(mixed $nombres, mixed $apellidos): string
    {
        $full = trim(trim((string) $nombres).' '.trim((string) $apellidos));

        return $full !== '' ? $full : '—';
    }

    /**
     * @param  list<string>  $keys
     * @return array<string, array{ventas: int, ingresos: float}>
     */
    private function emptyBreakdown(array $keys): array
    {
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = ['ventas' => 0, 'ingresos' => 0.0];
        }

        return $out;
    }

    /**
     * @param  list<string>  $tipos
     * @param  list<string>  $metodos
     * @return array<string, mixed>
     */
    private function emptyPayload(
        string $periodoKey,
        CarbonInterface $start,
        CarbonInterface $end,
        array $tipos,
        array $metodos,
    ): array {
        return [
            'moneda' => $this->resolveMoneda(),
            'filtros' => [
                'fecha_desde' => $start->toDateString(),
                'fecha_hasta' => $end->toDateString(),
                'periodo' => $periodoKey,
                'tipos' => $tipos,
                'metodos' => $metodos,
            ],
            'totales' => ['ventas' => 0, 'ingresos' => 0.0],
            'por_tipo' => $this->emptyBreakdown(self::TIPOS),
            'por_metodo' => $this->emptyBreakdown(self::METODOS),
            'items' => [],
        ];
    }

    private function resolveMoneda(): string
    {
        if (! Schema::hasTable('cfg_clinic_settings')) {
            return 'PEN';
        }

        $moneda = ClinicSetting::query()->value('moneda');

        return is_string($moneda) && $moneda !== '' ? strtoupper($moneda) : 'PEN';
    }
}
