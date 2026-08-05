<?php

declare(strict_types=1);

namespace App\Support\Venta;

use App\Models\ConsultaCargo;
use App\Support\Tenancy\TenantModuleAccess;
use App\Tenancy\TenantManager;
use Illuminate\Http\Request;

/**
 * Lista pre-cuentas confirmadas pendientes de cobro para el POS.
 */
final class PrecuentasPendientesLister
{
    /**
     * @return list<array{
     *     id: string,
     *     origen: 'consulta'|'grooming'|'hotel'|'internamiento',
     *     origen_id: string,
     *     origen_label: string,
     *     propietario_id: string|null,
     *     propietario_nombre: string|null,
     *     paciente_nombre: string|null,
     *     total: string,
     *     moneda: string,
     *     confirmado_at: string|null,
     *     url_cobrar: string
     * }>
     */
    public function list(Request $request): array
    {
        $user = $request->user();
        if ($user === null || ! $user->can('ventas.create')) {
            return [];
        }

        try {
            $cargos = ConsultaCargo::query()
                ->whereNull('venta_id')
                ->where('estado', ConsultaCargo::ESTADO_CONFIRMADO)
                ->where('total', '>', 0)
                ->with([
                    'consulta.historiaClinica.paciente' => fn ($q) => $q->withTrashed(),
                    'consulta.historiaClinica.paciente.propietario' => fn ($q) => $q->withTrashed(),
                    'groomingTurno.paciente' => fn ($q) => $q->withTrashed(),
                    'groomingTurno.paciente.propietario' => fn ($q) => $q->withTrashed(),
                    'hotelEstancia.paciente' => fn ($q) => $q->withTrashed(),
                    'hotelEstancia.paciente.propietario' => fn ($q) => $q->withTrashed(),
                    'internamiento.paciente' => fn ($q) => $q->withTrashed(),
                    'internamiento.paciente.propietario' => fn ($q) => $q->withTrashed(),
                ])
                ->orderByDesc('updated_at')
                ->limit(100)
                ->get();
        } catch (\Throwable $e) {
            report($e);

            return [];
        }

        $canConsulta = $user->can('consulta-cargos.cobrar');
        $canGrooming = $user->can('grooming.view');
        $tenant = app(TenantManager::class)->current()?->tenant;
        $canHotel = $user->can('hotel.view') && TenantModuleAccess::isEnabled($tenant, 'hotel');
        $canInternamiento = $user->can('consulta-cargos.cobrar');

        $out = [];
        foreach ($cargos as $cargo) {
            try {
                $row = $this->mapCargo($cargo, $canConsulta, $canGrooming, $canHotel, $canInternamiento);
                if ($row !== null) {
                    $out[] = $row;
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        return $out;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function mapCargo(
        ConsultaCargo $cargo,
        bool $canConsulta,
        bool $canGrooming,
        bool $canHotel,
        bool $canInternamiento,
    ): ?array {
        if ($cargo->grooming_turno_id && $canGrooming) {
            $turno = $cargo->groomingTurno;
            $pac = $turno?->paciente;
            $prop = $pac?->propietario;

            return [
                'id' => $cargo->id,
                'origen' => 'grooming',
                'origen_id' => $cargo->grooming_turno_id,
                'origen_label' => 'Grooming',
                'propietario_id' => $prop?->id,
                'propietario_nombre' => $prop?->displayName(),
                'paciente_nombre' => $pac?->nombre,
                'total' => (string) $cargo->total,
                'moneda' => (string) $cargo->moneda,
                'confirmado_at' => $cargo->updated_at?->toIso8601String(),
                'url_cobrar' => route('caja.ventas.create-desde-grooming', [
                    'grooming_turno' => $cargo->grooming_turno_id,
                ], absolute: false),
            ];
        }

        if ($cargo->hotel_estancia_id && $canHotel) {
            $est = $cargo->hotelEstancia;
            $pac = $est?->paciente;
            $prop = $pac?->propietario;

            return [
                'id' => $cargo->id,
                'origen' => 'hotel',
                'origen_id' => $cargo->hotel_estancia_id,
                'origen_label' => 'Hotel',
                'propietario_id' => $prop?->id,
                'propietario_nombre' => $prop?->displayName(),
                'paciente_nombre' => $pac?->nombre,
                'total' => (string) $cargo->total,
                'moneda' => (string) $cargo->moneda,
                'confirmado_at' => $cargo->updated_at?->toIso8601String(),
                'url_cobrar' => route('caja.ventas.create-desde-hotel', [
                    'hotel_estancia' => $cargo->hotel_estancia_id,
                ], absolute: false),
            ];
        }

        if ($cargo->consulta_id && $canConsulta) {
            $consulta = $cargo->consulta;
            $pac = $consulta?->historiaClinica?->paciente;
            $prop = $pac?->propietario;

            return [
                'id' => $cargo->id,
                'origen' => 'consulta',
                'origen_id' => $cargo->consulta_id,
                'origen_label' => 'Historia clínica',
                'propietario_id' => $prop?->id,
                'propietario_nombre' => $prop?->displayName(),
                'paciente_nombre' => $pac?->nombre,
                'total' => (string) $cargo->total,
                'moneda' => (string) $cargo->moneda,
                'confirmado_at' => $cargo->updated_at?->toIso8601String(),
                'url_cobrar' => route('caja.ventas.create-desde-consulta', [
                    'consulta' => $cargo->consulta_id,
                ], absolute: false),
            ];
        }

        if ($cargo->internamiento_id && $canInternamiento) {
            $int = $cargo->internamiento;
            $pac = $int?->paciente;
            $prop = $pac?->propietario;

            return [
                'id' => $cargo->id,
                'origen' => 'internamiento',
                'origen_id' => $cargo->internamiento_id,
                'origen_label' => 'Hospitalización',
                'propietario_id' => $prop?->id,
                'propietario_nombre' => $prop?->displayName(),
                'paciente_nombre' => $pac?->nombre,
                'total' => (string) $cargo->total,
                'moneda' => (string) $cargo->moneda,
                'confirmado_at' => $cargo->updated_at?->toIso8601String(),
                'url_cobrar' => route('caja.ventas.create-desde-internamiento', [
                    'internamiento' => $cargo->internamiento_id,
                ], absolute: false),
            ];
        }

        return null;
    }
}
