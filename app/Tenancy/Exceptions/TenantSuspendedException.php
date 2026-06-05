<?php

namespace App\Tenancy\Exceptions;

use App\Models\Tenant;
use RuntimeException;
use Throwable;

/**
 * Se lanza cuando el tenant existe pero su `estado` no está dentro de
 * `tenant.allowed_states` (típicamente `suspended` o `cancelled`).
 *
 * El middleware `ResolveTenant` la captura y devuelve 403 con un mensaje
 * apropiado para que el cliente entienda que su acceso fue bloqueado y
 * deba contactar a soporte. Conservamos referencia al modelo para que
 * un handler global pueda inspeccionar `estado` y mostrar pantallas
 * diferenciadas (suspendido vs. cancelado vs. trial expirado).
 */
class TenantSuspendedException extends RuntimeException
{
    public function __construct(
        public readonly Tenant $tenant,
        public readonly string $blockType = 'suspended',
        ?Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'Tenant "%s" no puede acceder (estado: %s, bloqueo: %s).',
                $tenant->slug ?? $tenant->getKey(),
                $tenant->estado ?? 'unknown',
                $blockType,
            ),
            0,
            $previous,
        );
    }
}
