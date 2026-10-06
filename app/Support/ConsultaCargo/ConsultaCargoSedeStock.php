<?php

namespace App\Support\ConsultaCargo;

use App\Models\CajaSesion;
use App\Models\ConsultaCargoLinea;
use App\Models\Sede;
use Illuminate\Support\Facades\Auth;

/**
 * Sede para descontar stock al confirmar una precuenta.
 * El registro clínico puede no traer sede: entonces vale la caja abierta del usuario
 * y, si hay productos, la primera sede activa.
 */
final class ConsultaCargoSedeStock
{
    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    public static function resolver(?string $sedeDelRegistro, array $lineas, ?string $tenantId): string
    {
        $sedeId = trim((string) $sedeDelRegistro);
        if ($sedeId !== '') {
            return $sedeId;
        }

        $sesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', Auth::id())
            ->first();
        $sedeId = trim((string) ($sesion?->sede_id ?? ''));
        if ($sedeId !== '') {
            return $sedeId;
        }

        if (! self::tieneProductos($lineas) || $tenantId === null || $tenantId === '') {
            return '';
        }

        return (string) (Sede::query()
            ->where('tenant_id', $tenantId)
            ->where('activa', true)
            ->whereNull('deleted_at')
            ->orderBy('nombre')
            ->value('id') ?? '');
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    public static function bloqueaConfirmacion(string $sedeId, array $lineas): bool
    {
        return $sedeId === '' && self::tieneProductos($lineas);
    }

    /**
     * @param  list<array<string, mixed>>  $lineas
     */
    private static function tieneProductos(array $lineas): bool
    {
        foreach ($lineas as $row) {
            if (($row['tipo_linea'] ?? '') === ConsultaCargoLinea::TIPO_PRODUCTO && ! empty($row['producto_id'])) {
                return true;
            }
        }

        return false;
    }
}
