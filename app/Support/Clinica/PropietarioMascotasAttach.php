<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\Paciente;
use App\Models\Propietario;

/**
 * Crea mascotas junto al alta de un propietario (modal unificado).
 */
final class PropietarioMascotasAttach
{
    /**
     * @param  array<string, mixed>  $validated
     * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
     */
    public static function split(array $validated): array
    {
        $raw = $validated['mascotas'] ?? [];
        unset($validated['mascotas']);

        $rows = [];
        if (is_array($raw)) {
            foreach ($raw as $item) {
                if (! is_array($item)) {
                    continue;
                }
                $nombre = trim((string) ($item['nombre'] ?? ''));
                if ($nombre === '') {
                    continue;
                }
                $rows[] = $item;
            }
        }

        return [$validated, $rows];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    public static function create(Propietario $propietario, array $rows, ?string $userId): int
    {
        $created = 0;
        foreach ($rows as $row) {
            Paciente::query()->create([
                'propietario_id' => $propietario->id,
                'nombre' => trim((string) $row['nombre']),
                'especie' => self::nullableString($row['especie'] ?? null),
                'raza' => self::nullableString($row['raza'] ?? null),
                'sexo' => self::sexo($row['sexo'] ?? null),
                'fecha_nacimiento' => self::nullableString($row['fecha_nacimiento'] ?? null),
                'peso_kg' => self::nullableString($row['peso_kg'] ?? null),
                'color' => self::nullableString($row['color'] ?? null),
                'esterilizado' => self::esterilizado($row['esterilizado'] ?? null),
                'notas' => self::nullableString($row['notas'] ?? null),
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);
            $created++;
        }

        return $created;
    }

    private static function nullableString(mixed $value): ?string
    {
        if (! is_string($value) && ! is_numeric($value)) {
            return null;
        }
        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private static function sexo(mixed $value): ?string
    {
        $s = is_string($value) ? strtoupper(trim($value)) : '';

        return in_array($s, ['M', 'H', 'U'], true) ? $s : null;
    }

    private static function esterilizado(mixed $value): ?bool
    {
        if (is_bool($value)) {
            return $value;
        }
        if ($value === 'yes' || $value === '1' || $value === 1) {
            return true;
        }
        if ($value === 'no' || $value === '0' || $value === 0) {
            return false;
        }

        return null;
    }
}
