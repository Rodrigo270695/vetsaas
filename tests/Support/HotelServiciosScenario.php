<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\HotelEstancia;
use App\Models\HotelEstanciaTarifa;
use App\Models\Paciente;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Tenant;
use App\Tenancy\Facades\Tenant as TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @return array{
 *     sede: Sede,
 *     propietario: Propietario,
 *     paciente: Paciente,
 *     sesion: CajaSesion,
 *     estancia: HotelEstancia,
 * }
 */
final class HotelServiciosScenario
{
    public static function seed(Tenant $tenant, string $slug, string $userId): array
    {
        $sede = Sede::query()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Sede Hotel Test',
            'codigo' => 'HT-'.Str::lower(Str::random(4)),
            'activa' => true,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return TenantContext::runForSlug($slug, function () use ($userId, $sede): array {
            if (! ClinicSetting::query()->exists()) {
                ClinicSetting::query()->create([
                    'moneda' => 'PEN',
                    'igv_porcentaje' => 18,
                    'precio_incluye_igv' => true,
                    'razon_social' => 'Clínica Test Hotel',
                    'nombre_comercial' => 'Hotel Test',
                    'updated_by_id' => $userId,
                ]);
            }

            $prop = Propietario::query()->create([
                'nombres' => 'Cliente',
                'apellidos' => 'Hotel Test',
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $paciente = Paciente::query()->create([
                'propietario_id' => $prop->id,
                'nombre' => 'Michi',
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

            HotelEstanciaTarifa::query()->create([
                'tipo_estancia' => 'habitacion_estandar',
                'precio_lista' => '30.00',
                'moneda' => 'PEN',
                'activo' => true,
            ]);

            $ingreso = Carbon::parse('2026-03-10 10:00:00', 'America/Lima');
            $egreso = Carbon::parse('2026-03-12 18:00:00', 'America/Lima');

            $estancia = HotelEstancia::query()->create([
                'paciente_id' => $paciente->id,
                'ingreso_at' => $ingreso,
                'egreso_at' => $egreso,
                'estado' => HotelEstancia::ESTADO_COMPLETADA,
                'tipo_estancia' => 'habitacion_estandar',
                'tipo_detalle' => null,
                'notas' => null,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            return [
                'sede' => $sede,
                'propietario' => $prop,
                'paciente' => $paciente,
                'sesion' => $sesion,
                'estancia' => $estancia,
            ];
        });
    }
}
