<?php

declare(strict_types=1);

namespace App\Services\Cotizacion;

use App\Models\ClinicSetting;
use App\Models\Cotizacion;
use App\Models\CotizacionLinea;
use App\Support\Cotizacion\CotizacionTotales;
use Illuminate\Support\Facades\DB;

final class CotizacionService
{
    /**
     * @param  array<string, mixed>  $data  validado por CotizacionRequest
     */
    public function crear(array $data, string $userId): Cotizacion
    {
        return DB::transaction(function () use ($data, $userId): Cotizacion {
            $anio = (int) date('Y', strtotime((string) $data['fecha']));
            $ultimo = Cotizacion::withTrashed()
                ->where('anio', $anio)
                ->orderByDesc('correlativo')
                ->lockForUpdate()
                ->first(['correlativo']);
            $correlativo = ($ultimo?->correlativo ?? 0) + 1;

            $cotizacion = new Cotizacion([
                'numero' => sprintf('COT-%d-%05d', $anio, $correlativo),
                'anio' => $anio,
                'correlativo' => $correlativo,
                'estado' => Cotizacion::ESTADO_EMITIDA,
                'created_by_id' => $userId,
            ]);

            $this->guardar($cotizacion, $data, $userId);

            return $cotizacion;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function actualizar(Cotizacion $cotizacion, array $data, string $userId): Cotizacion
    {
        return DB::transaction(function () use ($cotizacion, $data, $userId): Cotizacion {
            $this->guardar($cotizacion, $data, $userId);

            return $cotizacion;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardar(Cotizacion $cotizacion, array $data, string $userId): void
    {
        $clinic = ClinicSetting::current();
        $igvPct = $cotizacion->exists
            ? (float) $cotizacion->igv_porcentaje
            : $clinic->igvPorcentajeEfectivo();
        $incluyeIgv = $cotizacion->exists
            ? (bool) $cotizacion->precio_incluye_igv
            : (bool) $clinic->precio_incluye_igv;

        $lineas = array_values(array_map(static fn (array $l): array => [
            'tipo_linea' => (string) $l['tipo_linea'],
            'producto_id' => $l['tipo_linea'] === CotizacionLinea::TIPO_PRODUCTO ? ($l['producto_id'] ?? null) : null,
            'descripcion' => trim((string) $l['descripcion']),
            'unidad' => filled($l['unidad'] ?? null) ? trim((string) $l['unidad']) : null,
            'cantidad' => (float) $l['cantidad'],
            'precio_unitario' => (float) $l['precio_unitario'],
            'descuento_pct' => (float) ($l['descuento_pct'] ?? 0),
        ], (array) $data['lineas']));

        $totales = CotizacionTotales::calcular($lineas, $igvPct, $incluyeIgv);

        $cotizacion->fill([
            'sede_id' => $data['sede_id'] ?? null,
            'propietario_id' => $data['propietario_id'] ?? null,
            'paciente_id' => filled($data['propietario_id'] ?? null) ? ($data['paciente_id'] ?? null) : null,
            'cliente_nombre' => trim((string) $data['cliente_nombre']),
            'cliente_documento' => $this->nullable($data['cliente_documento'] ?? null),
            'cliente_telefono' => $this->nullable($data['cliente_telefono'] ?? null),
            'cliente_email' => $this->nullable($data['cliente_email'] ?? null),
            'fecha' => $data['fecha'],
            'vigencia_hasta' => $data['vigencia_hasta'],
            'moneda' => $cotizacion->moneda ?: ((string) ($clinic->moneda ?: 'PEN')),
            'igv_porcentaje' => $igvPct,
            'precio_incluye_igv' => $incluyeIgv,
            'subtotal' => $totales['subtotal'],
            'igv_monto' => $totales['igv'],
            'descuento_monto' => $totales['descuento'],
            'total' => $totales['total'],
            'notas' => $this->nullable($data['notas'] ?? null),
            'updated_by_id' => $userId,
        ]);
        $cotizacion->save();

        $cotizacion->lineas()->delete();
        foreach ($lineas as $orden => $linea) {
            $cotizacion->lineas()->create([
                ...$linea,
                'total' => CotizacionTotales::lineaTotal(
                    $linea['precio_unitario'],
                    $linea['cantidad'],
                    $linea['descuento_pct'],
                ),
                'orden' => $orden,
            ]);
        }

        $cotizacion->unsetRelation('lineas');
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : null;
    }
}
