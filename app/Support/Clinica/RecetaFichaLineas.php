<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\Receta;
use Illuminate\Support\Facades\Schema;

/**
 * Recetas reales ligadas a una ficha de desparasitación o antipulgas.
 */
final class RecetaFichaLineas
{
    /**
     * @return list<array{id: string, emitida_at: ?string, estado: string, medicamentos: list<string>}>
     */
    public static function resumen(string $column, string $id): array
    {
        if (! self::columnaValida($column) || ! Schema::hasColumn('recetas', $column)) {
            return [];
        }

        return Receta::query()
            ->where($column, $id)
            ->with(['lineas:id,receta_id,nombre_medicamento'])
            ->orderByDesc('emitida_at')
            ->get()
            ->map(static function (Receta $receta): array {
                return [
                    'id' => $receta->id,
                    'emitida_at' => $receta->emitida_at?->toIso8601String(),
                    'estado' => $receta->estado,
                    'medicamentos' => $receta->lineas
                        ->pluck('nombre_medicamento')
                        ->map(static fn (mixed $nombre): string => trim((string) $nombre))
                        ->filter(static fn (string $nombre): bool => $nombre !== '')
                        ->take(4)
                        ->values()
                        ->all(),
                ];
            })
            ->all();
    }

    /**
     * Líneas para sembrar la precuenta. La cantidad queda en 1 porque la receta guarda posología, no unidades de venta.
     *
     * @return list<array{producto_id: ?string, nombre: string, cantidad: string}>
     */
    public static function paraCargo(string $column, string $id): array
    {
        if (! self::columnaValida($column) || ! Schema::hasColumn('recetas', $column)) {
            return [];
        }

        $recetas = Receta::query()
            ->where($column, $id)
            ->where('estado', '!=', Receta::ESTADO_ANULADA)
            ->with(['lineas:id,receta_id,producto_id,nombre_medicamento'])
            ->get();

        $out = [];
        foreach ($recetas as $receta) {
            foreach ($receta->lineas as $linea) {
                $nombre = trim((string) $linea->nombre_medicamento);
                if ($nombre === '') {
                    continue;
                }
                $out[] = [
                    'producto_id' => $linea->producto_id,
                    'nombre' => $nombre,
                    'cantidad' => '1',
                ];
            }
        }

        return $out;
    }

    private static function columnaValida(string $column): bool
    {
        return in_array($column, ['desparasitacion_id', 'antipulga_id'], true)
            && Schema::hasTable('recetas');
    }
}
