<?php

namespace App\Support\Inventario;

use App\Models\Compra;
use App\Models\MovimientoInventario;
use Illuminate\Support\Str;

/**
 * Texto legible para listados / export del kardex a partir de `notas` + compra vinculada.
 */
final class MovimientoNotasVista
{
    public static function fromModel(MovimientoInventario $m): string
    {
        $compra = $m->relationLoaded('compra') ? $m->compra : null;

        return self::formato($m->notas, $compra);
    }

    public static function formato(?string $notas, ?Compra $compra): string
    {
        $n = $notas !== null ? trim($notas) : '';

        if ($n !== '' && str_starts_with($n, '{')) {
            $decoded = json_decode($notas, true);
            if (is_array($decoded)) {
                $parts = [];
                if (isset($decoded['precio_unitario']) && $decoded['precio_unitario'] !== '' && $decoded['precio_unitario'] !== null) {
                    $parts[] = 'P. unit.: '.(string) $decoded['precio_unitario'];
                }
                if (isset($decoded['cantidad']) && $decoded['cantidad'] !== '' && $decoded['cantidad'] !== null) {
                    $parts[] = 'Cant.: '.(string) $decoded['cantidad'];
                }
                if ($parts !== []) {
                    return implode(' · ', $parts);
                }
            }
        }

        if ($compra !== null) {
            $doc = trim(implode('-', array_filter([(string) ($compra->serie ?? ''), (string) ($compra->numero_documento ?? '')], fn (string $v): bool => $v !== '')));
            $pref = $doc !== '' ? 'Compra '.$doc : 'Compra';
            if ($compra->anulada_at !== null) {
                $pref .= ' (anulada)';
            }
            if ($n !== '' && ! str_starts_with($n, '{')) {
                return $pref.' — '.Str::limit($n, 220);
            }

            return $pref;
        }

        return $n;
    }
}
