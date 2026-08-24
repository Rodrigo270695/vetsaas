<?php

declare(strict_types=1);

namespace App\Support\ConsultaCargo;

use App\Models\ConsultaCargo;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Elimina una pre-cuenta pendiente (sin venta) y revierte stock si estaba confirmada.
 */
final class ConsultaCargoPendingDestroyer
{
    public function __construct(
        private readonly ConsultaCargoStockSync $cargoStock,
    ) {}

    public function destroy(ConsultaCargo $cargo, ?string $userId): void
    {
        if ($cargo->venta_id !== null) {
            throw ValidationException::withMessages([
                'cargo' => __('consulta-cargos.flash.ya_cobrado_no_eliminable'),
            ]);
        }

        DB::transaction(function () use ($cargo, $userId): void {
            $cargo->load('lineas');

            foreach ($cargo->lineas as $linea) {
                $this->cargoStock->revertirLinea($linea, $userId);
            }

            $cargo->lineas()->delete();
            $cargo->delete();
        });
    }
}
