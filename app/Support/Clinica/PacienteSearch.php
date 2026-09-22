<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\Paciente;
use Illuminate\Database\Eloquent\Builder;

/**
 * Búsqueda de pacientes para combobox: nombre, especie, raza, microchip
 * y titular. Cada palabra debe coincidir, en cualquier orden.
 */
final class PacienteSearch
{
    /**
     * @return list<array{id: string, label: string, keywords: string}>
     */
    public static function opcionesActivas(string $search, int $limit = 40): array
    {
        $tokens = PropietarioSearch::tokens($search);
        if ($tokens === []) {
            return [];
        }

        $query = Paciente::query()
            ->with(['propietario:id,nombres,apellidos,razon_social,telefono'])
            ->where('activo', true);

        foreach ($tokens as $token) {
            $like = '%'.addcslashes($token, '%_\\').'%';
            $query->where(function (Builder $q) use ($like): void {
                $q->where('nombre', 'ILIKE', $like)
                    ->orWhere('especie', 'ILIKE', $like)
                    ->orWhere('raza', 'ILIKE', $like)
                    ->orWhere('microchip', 'ILIKE', $like)
                    ->orWhereHas('propietario', function (Builder $owner) use ($like): void {
                        $owner->where('nombres', 'ILIKE', $like)
                            ->orWhere('apellidos', 'ILIKE', $like)
                            ->orWhere('razon_social', 'ILIKE', $like)
                            ->orWhere('telefono', 'ILIKE', $like)
                            ->orWhereRaw("btrim(concat_ws(' ', nombres, apellidos)) ILIKE ?", [$like]);
                    });
            });
        }

        return $query
            ->orderBy('nombre')
            ->limit($limit)
            ->get(['id', 'nombre', 'especie', 'raza', 'microchip', 'propietario_id'])
            ->map(static function (Paciente $paciente): array {
                $owner = trim((string) ($paciente->propietario?->displayName() ?? ''));
                $label = $owner !== '' ? $paciente->nombre.' · '.$owner : (string) $paciente->nombre;
                $keywords = trim(implode(' ', array_filter([
                    $paciente->especie,
                    $paciente->raza,
                    $paciente->microchip,
                    $paciente->propietario?->telefono,
                ])));

                return [
                    'id' => (string) $paciente->id,
                    'label' => $label,
                    'keywords' => $keywords,
                ];
            })
            ->values()
            ->all();
    }
}
