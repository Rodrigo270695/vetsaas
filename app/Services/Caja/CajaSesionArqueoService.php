<?php

declare(strict_types=1);

namespace App\Services\Caja;

use App\Models\CajaSesion;
use App\Models\FelSerie;
use App\Models\Sede;
use App\Models\Venta;
use Illuminate\Support\Collection;

/**
 * Calcula el arqueo de una sesión: ventas, métodos de pago y comprobantes.
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
            ->where('caja_sesion_id', $sesion->getKey())
            ->get([
                'id',
                'total',
                'metodo_pago',
                'tipo_comprobante_sunat',
                'estado',
                'anulado_at',
            ]);

        $vigentes = $ventas->filter(static fn (Venta $v): bool => $v->anulado_at === null && ! $v->estaAnulada());
        $anuladas = $ventas->filter(static fn (Venta $v): bool => $v->anulado_at !== null || $v->estaAnulada());

        $metodos = $this->agruparMetodos($vigentes);
        $comprobantes = $this->agruparComprobantes($vigentes);

        $efectivoVentas = $this->sumMetodo($metodos, 'efectivo');
        $saldoApertura = $this->money((string) $sesion->saldo_apertura);
        $esperado = $this->add($saldoApertura, $efectivoVentas);

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

        return [
            'sesion_id' => (string) $sesion->getKey(),
            'sede_id' => (string) $sesion->sede_id,
            'sede_nombre' => $sedeNombre ? (string) $sedeNombre : '—',
            'moneda' => (string) $sesion->moneda,
            'estado' => (string) $sesion->estado,
            'opened_at' => $sesion->opened_at?->toIso8601String(),
            'closed_at' => $sesion->closed_at?->toIso8601String(),
            'ventas_count' => $vigentes->count(),
            'ventas_total' => $this->money((string) $vigentes->sum(static fn (Venta $v): float => (float) $v->total)),
            'anuladas_count' => $anuladas->count(),
            'anuladas_total' => $this->money((string) $anuladas->sum(static fn (Venta $v): float => (float) $v->total)),
            'comprobantes' => $comprobantes,
            'metodos' => $metodos,
            'saldo_apertura' => $saldoApertura,
            'efectivo_ventas' => $efectivoVentas,
            'efectivo_esperado' => $esperado,
            'efectivo_contado' => $contado,
            'diferencia' => $diferencia,
            'generated_at' => now()->toIso8601String(),
        ];
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
                // Otros CPE: agrupar con boletas para el arqueo operativo.
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
