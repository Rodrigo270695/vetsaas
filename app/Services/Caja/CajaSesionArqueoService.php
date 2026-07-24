<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\CajaEgreso;
use App\Models\CajaSesion;
use App\Models\ConsultaCargoLinea;
use App\Models\FelSerie;
use App\Models\Sede;
use App\Models\Venta;
use App\Models\VentaLinea;
use Illuminate\Support\Collection;

/**
 * Calcula el arqueo de una sesión: ventas, egresos, métodos de pago y comprobantes.
 */
final class CajaSesionArqueoService
{
    private const METODOS = ['efectivo', 'yape', 'plin', 'tarjeta', 'transferencia'];

    /**
     * @return array<string, mixed>
     */
    public function build(CajaSesion $sesion, ?string $efectivoContado = null): array
    {
        $ventas = Venta::query()
            ->with([
                'propietario:id,nombres,apellidos,razon_social',
                'paciente:id,nombre',
            ])
            ->where('caja_sesion_id', $sesion->getKey())
            ->orderBy('created_at')
            ->get([
                'id',
                'numero',
                'total',
                'metodo_pago',
                'tipo_comprobante_sunat',
                'estado',
                'anulado_at',
                'propietario_id',
                'paciente_id',
                'created_at',
                'fecha_pago',
            ]);

        // Vigentes = cobradas en el turno (pagado/parcial), no anuladas ni soft-deleted.
        $vigentes = $ventas->filter(static function (Venta $v): bool {
            if ($v->anulado_at !== null || $v->estaAnulada()) {
                return false;
            }

            return in_array($v->estado, [Venta::ESTADO_PAGADO, Venta::ESTADO_PARCIAL], true);
        });

        $anuladas = $ventas->filter(static fn (Venta $v): bool => $v->anulado_at !== null || $v->estaAnulada());
        $excluidas = $ventas->filter(static function (Venta $v) use ($vigentes, $anuladas): bool {
            return ! $vigentes->contains(static fn (Venta $x): bool => $x->getKey() === $v->getKey())
                && ! $anuladas->contains(static fn (Venta $x): bool => $x->getKey() === $v->getKey());
        });

        $metodos = $this->agruparMetodos($vigentes);
        $comprobantes = $this->agruparComprobantes($vigentes);
        $ventasDetalle = $this->detalleVentas($vigentes);
        $rubros = $this->desgloseRubros($vigentes);

        $egresos = CajaEgreso::query()
            ->with(['creadoPor:id,name'])
            ->where('caja_sesion_id', $sesion->getKey())
            ->orderBy('created_at')
            ->get();

        $egresosTotal = '0.00';
        $egresosDetalle = [];
        foreach ($egresos as $egreso) {
            $monto = $this->money((string) $egreso->monto);
            $egresosTotal = $this->add($egresosTotal, $monto);
            $egresosDetalle[] = [
                'id' => (string) $egreso->getKey(),
                'monto' => $monto,
                'motivo' => (string) $egreso->motivo,
                'motivo_label' => CajaEgreso::labelMotivo((string) $egreso->motivo),
                'notas' => $egreso->notas,
                'created_at' => $egreso->created_at?->toIso8601String(),
                'created_by' => $egreso->creadoPor?->name,
            ];
        }

        $ventasTotal = '0.00';
        foreach ($vigentes as $venta) {
            $ventasTotal = $this->add($ventasTotal, $this->money((string) $venta->total));
        }

        $anuladasTotal = '0.00';
        foreach ($anuladas as $venta) {
            $anuladasTotal = $this->add($anuladasTotal, $this->money((string) $venta->total));
        }

        $efectivoVentas = $this->sumMetodo($metodos, 'efectivo');
        $noEfectivoTotal = $this->sub($ventasTotal, $efectivoVentas);
        $saldoApertura = $this->money((string) $sesion->saldo_apertura);
        // Efectivo esperado = caja física (apertura + ventas en efectivo − egresos).
        $esperado = $this->sub($this->add($saldoApertura, $efectivoVentas), $egresosTotal);

        $contado = null;
        $diferencia = null;
        if ($efectivoContado !== null && $efectivoContado !== '') {
            $contado = $this->money($efectivoContado);
            $diferencia = $this->sub($contado, $esperado);
        } elseif ($sesion->saldo_cierre_efectivo !== null) {
            $contado = $this->money((string) $sesion->saldo_cierre_efectivo);
            $diferencia = $this->sub($contado, $esperado);
        }

        $sedeNombre = Sede::query()->whereKey($sesion->sede_id)->value('nombre');
        $metodosChart = $this->chartMetodos($metodos, $ventasTotal);

        return [
            'sesion_id' => (string) $sesion->getKey(),
            'sede_id' => (string) $sesion->sede_id,
            'sede_nombre' => $sedeNombre ? (string) $sedeNombre : '—',
            'moneda' => (string) $sesion->moneda,
            'estado' => (string) $sesion->estado,
            'opened_at' => $sesion->opened_at?->toIso8601String(),
            'closed_at' => $sesion->closed_at?->toIso8601String(),
            'ventas_count' => $vigentes->count(),
            'ventas_total' => $ventasTotal,
            'productos_total' => $rubros['productos_total'],
            'servicios_total' => $rubros['servicios_total'],
            'no_efectivo_total' => $noEfectivoTotal,
            'anuladas_count' => $anuladas->count(),
            'anuladas_total' => $anuladasTotal,
            'excluidas_count' => $excluidas->count(),
            'comprobantes' => $comprobantes,
            'metodos' => $metodos,
            'metodos_chart' => $metodosChart,
            'ventas' => $ventasDetalle,
            'egresos_count' => $egresos->count(),
            'egresos_total' => $egresosTotal,
            'egresos' => $egresosDetalle,
            'saldo_apertura' => $saldoApertura,
            'efectivo_ventas' => $efectivoVentas,
            'efectivo_esperado' => $esperado,
            'efectivo_contado' => $contado,
            'diferencia' => $diferencia,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Reparte el total cobrado de cada venta entre productos y servicios
     * (proporcional al subtotal de líneas). Así el cierre refleja ambos rubros.
     *
     * @param  Collection<int, Venta>  $ventas
     * @return array{productos_total: string, servicios_total: string}
     */
    private function desgloseRubros(Collection $ventas): array
    {
        $productos = '0.00';
        $servicios = '0.00';

        if ($ventas->isEmpty()) {
            return ['productos_total' => $productos, 'servicios_total' => $servicios];
        }

        $ids = $ventas->modelKeys();
        $lineas = VentaLinea::query()
            ->whereIn('venta_id', $ids)
            ->get(['venta_id', 'tipo_linea', 'producto_id', 'subtotal'])
            ->groupBy('venta_id');

        foreach ($ventas as $venta) {
            $ventaTotal = $this->money((string) $venta->total);
            $ventaLineas = $lineas->get($venta->getKey(), collect());

            if ($ventaLineas->isEmpty()) {
                $servicios = $this->add($servicios, $ventaTotal);

                continue;
            }

            $sumSub = 0.0;
            $prodSub = 0.0;
            foreach ($ventaLineas as $linea) {
                $sub = (float) $linea->subtotal;
                $sumSub += $sub;
                if ($this->esLineaProducto($linea)) {
                    $prodSub += $sub;
                }
            }

            if ($sumSub <= 0) {
                $servicios = $this->add($servicios, $ventaTotal);

                continue;
            }

            $prodShare = $this->money((string) (((float) $ventaTotal) * ($prodSub / $sumSub)));
            $servShare = $this->sub($ventaTotal, $prodShare);
            $productos = $this->add($productos, $prodShare);
            $servicios = $this->add($servicios, $servShare);
        }

        return [
            'productos_total' => $productos,
            'servicios_total' => $servicios,
        ];
    }

    private function esLineaProducto(VentaLinea $linea): bool
    {
        if ($linea->tipo_linea === ConsultaCargoLinea::TIPO_SERVICIO) {
            return false;
        }

        if ($linea->tipo_linea === ConsultaCargoLinea::TIPO_PRODUCTO) {
            return true;
        }

        return $linea->producto_id !== null;
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return list<array{codigo: string, count: int, total: string}>
     */
    private function agruparMetodos(Collection $ventas): array
    {
        $buckets = [];
        foreach (self::METODOS as $codigo) {
            $buckets[$codigo] = ['codigo' => $codigo, 'count' => 0, 'total' => '0.00'];
        }
        $buckets['otro'] = ['codigo' => 'otro', 'count' => 0, 'total' => '0.00'];

        foreach ($ventas as $venta) {
            $codigo = is_string($venta->metodo_pago) && $venta->metodo_pago !== ''
                ? $venta->metodo_pago
                : 'otro';

            if (! isset($buckets[$codigo])) {
                $codigo = 'otro';
            }

            $buckets[$codigo]['count']++;
            $buckets[$codigo]['total'] = $this->add(
                $buckets[$codigo]['total'],
                $this->money((string) $venta->total),
            );
        }

        return array_values(array_filter(
            $buckets,
            static fn (array $row): bool => $row['count'] > 0 || $row['codigo'] === 'efectivo',
        ));
    }

    /**
     * @param  list<array{codigo: string, count: int, total: string}>  $metodos
     * @return list<array{codigo: string, count: int, total: string, pct: float, bar_pct: float}>
     */
    private function chartMetodos(array $metodos, string $ventasTotal): array
    {
        $total = (float) $ventasTotal;
        $out = [];

        foreach ($metodos as $row) {
            if ((int) $row['count'] <= 0) {
                continue;
            }

            $monto = (float) $row['total'];
            $pct = $total > 0 ? round(($monto / $total) * 100, 1) : 0.0;

            $out[] = [
                'codigo' => $row['codigo'],
                'count' => (int) $row['count'],
                'total' => $this->money($row['total']),
                'pct' => $pct,
                'bar_pct' => max(2.0, min(100.0, $pct)),
            ];
        }

        return $out;
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return array{
     *     tickets: array{count: int, total: string},
     *     boletas: array{count: int, total: string},
     *     facturas: array{count: int, total: string}
     * }
     */
    private function agruparComprobantes(Collection $ventas): array
    {
        $out = [
            'tickets' => ['count' => 0, 'total' => '0.00'],
            'boletas' => ['count' => 0, 'total' => '0.00'],
            'facturas' => ['count' => 0, 'total' => '0.00'],
        ];

        foreach ($ventas as $venta) {
            $tipo = $venta->tipo_comprobante_sunat;
            $key = 'tickets';

            if ((int) $tipo === FelSerie::TIPO_FACTURA) {
                $key = 'facturas';
            } elseif ((int) $tipo === FelSerie::TIPO_BOLETA) {
                $key = 'boletas';
            } elseif (FelSerie::esTipoSunat($tipo !== null ? (int) $tipo : null)) {
                $key = 'boletas';
            }

            $out[$key]['count']++;
            $out[$key]['total'] = $this->add(
                $out[$key]['total'],
                $this->money((string) $venta->total),
            );
        }

        return $out;
    }

    /**
     * @param  Collection<int, Venta>  $ventas
     * @return list<array{
     *     id: string,
     *     numero: string,
     *     fecha: string|null,
     *     cliente: string,
     *     metodo: string,
     *     comprobante: string,
     *     total: string,
     *     estado: string
     * }>
     */
    private function detalleVentas(Collection $ventas): array
    {
        $tz = (string) config('app.timezone', 'UTC');
        $rows = [];

        foreach ($ventas as $venta) {
            $tipo = $venta->tipo_comprobante_sunat;
            $comprobante = 'ticket';
            if ((int) $tipo === FelSerie::TIPO_FACTURA) {
                $comprobante = 'factura';
            } elseif ((int) $tipo === FelSerie::TIPO_BOLETA || FelSerie::esTipoSunat($tipo !== null ? (int) $tipo : null)) {
                $comprobante = 'boleta';
            }

            $cliente = $venta->propietario?->displayName() ?: '—';
            if ($venta->paciente?->nombre) {
                $cliente .= ' / '.$venta->paciente->nombre;
            }

            $fecha = $venta->fecha_pago ?? $venta->created_at;

            $rows[] = [
                'id' => (string) $venta->getKey(),
                'numero' => (string) ($venta->numero ?: '—'),
                'fecha' => $fecha?->timezone($tz)->toIso8601String(),
                'cliente' => $cliente,
                'metodo' => is_string($venta->metodo_pago) && $venta->metodo_pago !== ''
                    ? $venta->metodo_pago
                    : 'otro',
                'comprobante' => $comprobante,
                'total' => $this->money((string) $venta->total),
                'estado' => (string) $venta->estado,
            ];
        }

        return $rows;
    }

    /**
     * @param  list<array{codigo: string, count: int, total: string}>  $metodos
     */
    private function sumMetodo(array $metodos, string $codigo): string
    {
        foreach ($metodos as $row) {
            if ($row['codigo'] === $codigo) {
                return $this->money($row['total']);
            }
        }

        return '0.00';
    }

    private function money(string $value): string
    {
        if (! is_numeric($value)) {
            return '0.00';
        }

        return number_format((float) $value, 2, '.', '');
    }

    private function add(string $a, string $b): string
    {
        return $this->money((string) ((float) $a + (float) $b));
    }

    private function sub(string $a, string $b): string
    {
        return $this->money((string) ((float) $a - (float) $b));
    }
}
