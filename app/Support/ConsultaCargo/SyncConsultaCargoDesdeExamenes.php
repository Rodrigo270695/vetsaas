<?php

declare(strict_types=1);

namespace App\Support\ConsultaCargo;

use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\ConsultaCargo;
use App\Models\ConsultaCargoLinea;
use App\Models\ConsultaExamen;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Al abrir la precuenta en borrador, agrega líneas de servicio
 * desde los exámenes complementarios (servicios clínicos) de la HC
 * que aún no estén en el cargo (match por concepto).
 */
final class SyncConsultaCargoDesdeExamenes
{
    public function sync(Consulta $consulta, ConsultaCargo $cargo): bool
    {
        if (! $cargo->esBorrador() || $cargo->venta_id !== null) {
            return false;
        }

        $consulta->loadMissing([
            'examenes' => fn ($q) => $q->orderBy('orden')->with('servicioClinico:id,nombre,precio_lista'),
        ]);

        /** @var \Illuminate\Support\Collection<int, ConsultaExamen> $examenes */
        $examenes = $consulta->examenes;
        if ($examenes->isEmpty()) {
            return false;
        }

        $cargo->loadMissing(['lineas' => fn ($q) => $q->orderBy('orden')]);

        $conceptosExistentes = $cargo->lineas
            ->filter(fn (ConsultaCargoLinea $l) => $l->tipo_linea === ConsultaCargoLinea::TIPO_SERVICIO)
            ->map(fn (ConsultaCargoLinea $l) => mb_strtolower(trim($l->concepto)))
            ->filter()
            ->values()
            ->all();

        $nuevas = [];
        foreach ($examenes as $examen) {
            $nombre = trim((string) $examen->nombre);
            if ($nombre === '') {
                continue;
            }
            $key = mb_strtolower($nombre);
            if (in_array($key, $conceptosExistentes, true)) {
                continue;
            }

            $precio = $examen->servicioClinico?->precio_lista;
            $precioUnitario = $precio !== null && is_numeric($precio)
                ? number_format((float) $precio, 4, '.', '')
                : '0.0000';

            $nuevas[] = [
                'concepto' => mb_substr($nombre, 0, 500),
                'precio_unitario' => $precioUnitario,
            ];
            $conceptosExistentes[] = $key;
        }

        if ($nuevas === []) {
            return false;
        }

        $cfg = ClinicSetting::query()->first();
        if ($cfg === null) {
            return false;
        }

        DB::transaction(function () use ($cargo, $nuevas, $cfg): void {
            $orden = (int) ($cargo->lineas->max('orden') ?? -1) + 1;

            foreach ($nuevas as $row) {
                ConsultaCargoLinea::query()->create([
                    'consulta_cargo_id' => $cargo->id,
                    'tipo_linea' => ConsultaCargoLinea::TIPO_SERVICIO,
                    'producto_id' => null,
                    'concepto' => $row['concepto'],
                    'cantidad' => '1.0000',
                    'precio_unitario' => $row['precio_unitario'],
                    'descuento_importe' => 0,
                    'orden' => $orden++,
                ]);
            }

            $cargo->load(['lineas' => fn ($q) => $q->orderBy('orden')]);

            $totales = ConsultaCargoTotales::fromLineas(
                $cargo->lineas->map(static fn (ConsultaCargoLinea $l): array => [
                    'cantidad' => $l->cantidad,
                    'precio_unitario' => $l->precio_unitario,
                    'descuento_importe' => $l->descuento_importe,
                ])->all(),
                (bool) $cfg->precio_incluye_igv,
                $cfg->igvPorcentajeEfectivo(),
            );

            $cargo->update([
                'subtotal_sin_igv' => $totales['subtotal_sin_igv'],
                'igv_importe' => $totales['igv_importe'],
                'total' => $totales['total'],
                'updated_by_id' => Auth::id(),
            ]);
        });

        $cargo->unsetRelation('lineas');

        return true;
    }
}
