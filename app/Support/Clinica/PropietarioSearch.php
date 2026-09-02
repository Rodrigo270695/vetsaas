<?php

declare(strict_types=1);

namespace App\Support\Clinica;

use App\Models\Propietario;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Búsqueda de titulares: cada palabra debe coincidir en nombre, apellido,
 * razón social, DNI, correo o teléfono. El orden no importa
 * (“rodrigo granja” = “granja rodrigo”).
 */
final class PropietarioSearch
{
    /**
     * @param  Builder<Propietario>  $query
     */
    public static function apply(Builder $query, string $search): void
    {
        $tokens = self::tokens($search);
        if ($tokens === []) {
            return;
        }

        $query->where(function (Builder $outer) use ($tokens): void {
            foreach ($tokens as $token) {
                $outer->where(function (Builder $q) use ($token): void {
                    self::matchToken($q, $token);
                });
            }
        });
    }

    /**
     * @return Collection<int, array{id: string, label: string, doc: string|null}>
     */
    public static function opcionesActivas(string $search = '', int $limit = 40): Collection
    {
        $query = Propietario::query()->where('activo', true);
        self::apply($query, $search);

        return $query
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'numero_documento'])
            ->map(static fn (Propietario $pr): array => self::opcion($pr))
            ->values();
    }

    /**
     * @return array{id: string, label: string, doc: string|null}
     */
    public static function opcion(Propietario $pr): array
    {
        $label = trim((string) ($pr->razon_social ?: ''));
        if ($label === '') {
            $label = trim(implode(' ', array_filter([$pr->nombres, $pr->apellidos])));
        }

        $doc = trim((string) ($pr->numero_documento ?? ''));

        return [
            'id' => $pr->id,
            'label' => $label !== '' ? $label : '—',
            'doc' => $doc !== '' ? $doc : null,
        ];
    }

    /**
     * @return list<string>
     */
    public static function tokens(string $search): array
    {
        $normalized = trim(preg_replace('/\s+/u', ' ', $search) ?? '');
        if ($normalized === '') {
            return [];
        }

        $parts = preg_split('/\s+/u', $normalized) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $part = trim($part);
            if ($part === '') {
                continue;
            }

            $digits = preg_replace('/\D+/', '', $part) ?? '';
            $isDocHint = strlen($digits) >= 3 && strlen($digits) >= (int) (mb_strlen($part) * 0.6);

            if (! $isDocHint && mb_strlen($part) < 2) {
                continue;
            }

            $out[] = $isDocHint ? $digits : $part;
        }

        return array_values(array_unique($out));
    }

    /**
     * @param  Builder<Propietario>  $q
     */
    private static function matchToken(Builder $q, string $token): void
    {
        $like = '%'.addcslashes($token, '%_\\').'%';

        $q->where('nombres', 'ILIKE', $like)
            ->orWhere('apellidos', 'ILIKE', $like)
            ->orWhere('razon_social', 'ILIKE', $like)
            ->orWhere('email', 'ILIKE', $like)
            ->orWhere('telefono', 'ILIKE', $like)
            ->orWhere('telefono_alt', 'ILIKE', $like)
            ->orWhere('numero_documento', 'ILIKE', $like)
            ->orWhereRaw("btrim(concat_ws(' ', nombres, apellidos)) ILIKE ?", [$like])
            ->orWhereRaw("btrim(concat_ws(' ', apellidos, nombres)) ILIKE ?", [$like]);
    }
}
