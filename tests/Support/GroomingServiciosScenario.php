<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\GroomingServicioTarifa;
use App\Models\GroomingTurno;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Tenant;
use App\Tenancy\Facades\Tenant as TenantContext;
use Illuminate\Support\Str;

/**
 * @return array{
 *     sede: Sede,
 *     propietario: Propietario,
 *     paciente: Paciente,
 *     sesion: CajaSesion,
 *     turno: GroomingTurno,
 *     tarifa: GroomingServicioTarifa,
 * }
 */
final class GroomingServiciosScenario
{
    public static function seed(Tenant $tenant, string $slug, string $userId): array
    {
        $sede = Sede::query()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Sede Grooming Test',
            'codigo' => 'GR-'.Str::upper(Str::random(4)),
            'activa' => true,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return TenantContext::runForSlug($slug, function () use ($userId, $sede) {
            if (! ClinicSetting::query()->exists()) {
                ClinicSetting::query()->create([
                    'moneda' => 'PEN',
                    'igv_porcentaje' => 18,
                    'precio_incluye_igv' => true,
                    'razon_social' => 'Clínica Grooming Test',
                    'nombre_comercial' => 'Grooming Test',
                    'updated_by_id' => $userId,
                ]);
            }

            $prop = Propietario::query()->create([
                'nombres' => 'Dueño',
                'apellidos' => 'Grooming',
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $paciente = Paciente::query()->create([
                'propietario_id' => $prop->id,
                'nombre' => 'Rex',
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $sesion = CajaSesion::query()->create([
                'sede_id' => $sede->id,
                'estado' => CajaSesion::ESTADO_ABIERTA,
                'moneda' => 'PEN',
                'saldo_apertura' => '0.00',
                'opened_at' => now(),
                'opened_by_id' => $userId,
            ]);

            $tarifa = GroomingServicioTarifa::query()->create([
                'servicio' => 'bano_higienico',
                'precio_lista' => '55.00',
                'moneda' => 'PEN',
                'activo' => true,
            ]);

            $turno = GroomingTurno::query()->create([
                'paciente_id' => $paciente->id,
                'sede_id' => $sede->id,
                'responsable_id' => $userId,
                'inicio_at' => now()->subHour(),
                'duracion_minutos' => 60,
                'estado' => GroomingTurno::ESTADO_COMPLETADA,
                'servicio' => 'bano_higienico',
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            return [
                'sede' => $sede,
                'propietario' => $prop,
                'paciente' => $paciente,
                'sesion' => $sesion,
                'turno' => $turno,
                'tarifa' => $tarifa,
            ];
        });
    }
}
