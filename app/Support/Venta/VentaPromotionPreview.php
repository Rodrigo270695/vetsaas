<?php

namespace App\Support\Venta;

use App\Models\ClinicSetting;
use App\Models\ConsultaCargoLinea;
use App\Models\GroomingTurno;
use App\Models\Producto;
use App\Services\Venta\PromotionCheckoutService;

final class VentaPromotionPreview
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{
     *     lineas: list<array<string, mixed>>,
     *     discount_amount: string,
     *     promotion_id: ?string,
     *     promotion_name: ?string,
     *     promotions_applied: list<array<string, mixed>>,
     *     subtotal: string,
     *     igv_monto: string,
     *     total: string,
     * }
     */
    public function preview(array $validated): array
    {
        $clinic = ClinicSetting::current();
        $igvPct = (float) (string) $clinic->igv_porcentaje;
        $precioIncluyeIgv = (bool) $clinic->precio_incluye_igv;
        $divisorIgv = 1 + ($igvPct / 100);

        $productoIds = collect($validated['lineas'])
            ->pluck('producto_id')
            ->filter(fn ($id) => is_string($id) && $id !== '')
            ->unique()
            ->values()
            ->all();

        $productos = $productoIds === []
            ? collect()
            : Producto::query()
                ->whereIn('id', $productoIds)
                ->where('activo', true)
                ->whereNull('deleted_at')
                ->get()
                ->keyBy('id');

        $lineasCalc = [];

        foreach ($validated['lineas'] as $row) {
            $pid = isset($row['producto_id']) && is_string($row['producto_id']) && $row['producto_id'] !== ''
                ? $row['producto_id']
                : null;
            $cantidad = (float) (string) $row['cantidad'];
            $tipoLinea = isset($row['tipo_linea']) && is_string($row['tipo_linea'])
                ? $row['tipo_linea']
                : ($pid !== null ? ConsultaCargoLinea::TIPO_PRODUCTO : ConsultaCargoLinea::TIPO_SERVICIO);

            if ($pid !== null) {
                $producto = $productos->get($pid);
                if ($producto === null) {
                    continue;
                }
                $precioLista = isset($row['precio_lista']) && $row['precio_lista'] !== ''
                    ? (float) (string) $row['precio_lista']
                    : (float) (string) ($producto->precio_venta ?? 0);
            } else {
                $precioLista = (float) (string) ($row['precio_lista'] ?? 0);
            }

            if ($precioIncluyeIgv) {
                $lineGross = round($cantidad * $precioLista, 2);
                $lineSub = $divisorIgv > 0 ? round($lineGross / $divisorIgv, 2) : $lineGross;
                $puSinIgv = $cantidad > 0 ? round($lineSub / $cantidad, 4) : 0.0;
            } else {
                $puSinIgv = $precioLista;
                $lineSub = round($cantidad * $puSinIgv, 2);
            }

            $lineasCalc[] = [
                'producto_id' => $pid,
                'tipo_linea' => $tipoLinea,
                'cantidad' => $cantidad,
                'precio_lista' => $precioLista,
                'precio_unitario' => $puSinIgv,
                'descuento_pct' => 0.0,
                'subtotal' => $lineSub,
                'promotion_id' => null,
            ];
        }

        $groomingServiceSlug = null;
        $groomingId = $validated['grooming_turno_id'] ?? null;
        if (is_string($groomingId) && $groomingId !== '') {
            $turno = GroomingTurno::query()->find($groomingId);
            if ($turno !== null) {
                $groomingServiceSlug = $turno->servicio;
                if (! is_string($validated['paciente_id'] ?? null) || $validated['paciente_id'] === '') {
                    $validated['paciente_id'] = $turno->paciente_id;
                }
            }
        }

        $promoResult = app(PromotionCheckoutService::class)->evaluate(
            [
                'propietario_id' => $validated['propietario_id'],
                'paciente_id' => $validated['paciente_id'] ?? null,
                'grooming_turno_id' => $validated['grooming_turno_id'] ?? null,
                'grooming_service_slug' => $groomingServiceSlug,
                'hotel_estancia_id' => $validated['hotel_estancia_id'] ?? null,
                'promotion_code' => $validated['promotion_code'] ?? null,
            ],
            $lineasCalc,
            $igvPct,
            $precioIncluyeIgv,
        );

        $lineas = $promoResult->lineas;
        $subtotal = array_sum(array_map(fn (array $l): float => (float) $l['subtotal'], $lineas));
        $subtotal = round($subtotal, 2);

        if ($precioIncluyeIgv) {
            $total = 0.0;
            foreach ($lineas as $line) {
                $total += round((float) $line['subtotal'] * $divisorIgv, 2);
            }
            $total = round($total, 2);
            $igv = round($total - $subtotal, 2);
        } else {
            $igv = round($subtotal * ($igvPct / 100), 2);
            $total = round($subtotal + $igv, 2);
        }

        return [
            'lineas' => $lineas,
            'discount_amount' => $promoResult->discount_amount,
            'promotion_id' => $promoResult->promotion_id,
            'promotion_name' => $promoResult->promotion_name,
            'promotions_applied' => $promoResult->promotions_applied,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'igv_monto' => number_format($igv, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ];
    }
}
