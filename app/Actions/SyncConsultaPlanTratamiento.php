<?php

namespace App\Actions;

use App\Models\Consulta;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

class SyncConsultaPlanTratamiento
{
    /**
     * @param  array<string, mixed>|null  $payload
     */
    public function handle(Consulta $consulta, ?array $payload, ?string $userId): void
    {
        if ($payload === null) {
            return;
        }

        $lineas = $payload['lineas'] ?? [];
        if (! is_array($lineas)) {
            $lineas = [];
        }

        $indicaciones = isset($payload['indicaciones']) ? trim((string) $payload['indicaciones']) : '';
        $fechaIni = isset($payload['fecha_inicio']) && $payload['fecha_inicio'] !== ''
            ? (string) $payload['fecha_inicio']
            : null;
        $fechaFin = isset($payload['fecha_fin']) && $payload['fecha_fin'] !== ''
            ? (string) $payload['fecha_fin']
            : null;

        $hasContent = $indicaciones !== ''
            || $fechaIni !== null
            || $fechaFin !== null
            || count($lineas) > 0;

        $existing = $consulta->planTratamiento;

        if (! $hasContent) {
            if ($existing !== null) {
                $existing->lineas()->delete();
                $existing->seguimientos()->delete();
                $existing->delete();
            }

            return;
        }

        $estado = isset($payload['estado']) ? (string) $payload['estado'] : 'activo';
        if (! in_array($estado, ['activo', 'completado', 'suspendido'], true)) {
            $estado = 'activo';
        }

        $attrs = [
            'fecha_inicio' => $fechaIni,
            'fecha_fin' => $fechaFin,
            'indicaciones' => $indicaciones === '' ? null : $indicaciones,
            'estado' => $estado,
            'updated_by_id' => $userId,
        ];

        if ($existing !== null) {
            $existing->update($attrs);
            $plan = $existing;
        } else {
            $plan = $consulta->planTratamiento()->create(array_merge($attrs, [
                'created_by_id' => $userId,
            ]));
        }

        $plan->lineas()->delete();

        foreach (array_values($lineas) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }
            $medicamento = trim((string) ($row['medicamento'] ?? ''));
            if ($medicamento === '') {
                continue;
            }
            $anadidoEn = $this->nullableDateString($row['anadido_en'] ?? null)
                ?? CarbonImmutable::now()->toDateString();

            $plan->lineas()->create([
                'producto_id' => $this->nullableUuid($row['producto_id'] ?? null),
                'cantidad' => $this->nullableDecimal($row['cantidad'] ?? null),
                'medicamento' => Str::limit($medicamento, 500, ''),
                'dosis' => $this->nullableString($row['dosis'] ?? null, 255),
                'unidad' => $this->nullableString($row['unidad'] ?? null, 64),
                'via' => $this->nullableString($row['via'] ?? null, 128),
                'frecuencia' => $this->nullableString($row['frecuencia'] ?? null, 255),
                'lote' => $this->nullableString($row['lote'] ?? null, 128),
                'notas' => $this->nullableText($row['notas'] ?? null),
                'anadido_en' => $anadidoEn,
                'sort_order' => $index,
            ]);
        }
    }

    private function nullableString(mixed $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : Str::limit($s, $max, '');
    }

    private function nullableText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $s = trim((string) $value);

        return $s === '' ? null : $s;
    }

    private function nullableDateString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '' || ! preg_match('/^\d{4}-\d{2}-\d{2}$/', $s)) {
            return null;
        }

        return $s;
    }

    private function nullableUuid(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        $s = trim((string) $value);
        if ($s === '' || ! Str::isUuid($s)) {
            return null;
        }

        return $s;
    }

    private function nullableDecimal(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (! is_numeric($value)) {
            return null;
        }
        $n = (float) $value;
        if ($n < 0) {
            return null;
        }

        return number_format($n, 3, '.', '');
    }
}
