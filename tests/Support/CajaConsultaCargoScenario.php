<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\Consulta;
use App\Models\ConsultaCargo;
use App\Models\ConsultaCargoLinea;
use App\Models\HistoriaClinica;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Tenant;
use App\Tenancy\Facades\Tenant as TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Escenario mínimo: consulta con pre-cuenta confirmada (servicio + producto),
 * sede en public, sesión de caja abierta y stock en sede.
 *
 * @return array{
 *     propietario: Propietario,
 *     paciente: Paciente,
 *     consulta: Consulta,
 *     cargo: ConsultaCargo,
 *     linea_servicio: ConsultaCargoLinea,
 *     linea_producto: ConsultaCargoLinea,
 *     producto: Producto,
 *     sede: Sede,
 *     sesion: CajaSesion,
 * }
 */
final class CajaConsultaCargoScenario
{
    public static function seed(Tenant $tenant, string $slug, string $userId): array
    {
        $sede = Sede::query()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Sede Test',
            'codigo' => 'ST-'.Str::upper(Str::random(4)),
            'activa' => true,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return TenantContext::runForSlug($slug, function () use ($userId, $sede) {
            self::ensureClinicSettings($userId);

            $prop = Propietario::query()->create([
                'nombres' => 'Cliente',
                'apellidos' => 'Cobro Test',
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $paciente = Paciente::query()->create([
                'propietario_id' => $prop->id,
                'nombre' => 'Firulais',
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $historia = HistoriaClinica::query()->create([
                'paciente_id' => $paciente->id,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $consulta = $historia->consultas()->create([
                'atendido_at' => now()->subHour(),
                'motivo' => 'Control',
                'veterinario_id' => $userId,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $producto = Producto::query()->create([
                'nombre' => 'Antipulgas Test',
                'slug' => 'antipulgas-'.Str::lower(Str::random(6)),
                'sku' => 'SKU-'.Str::upper(Str::random(5)),
                'precio_venta' => '20.00',
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            DB::table('existencias_sede')->insert([
                'id' => (string) Str::uuid(),
                'producto_id' => $producto->id,
                'sede_id' => $sede->id,
                'cantidad' => '50.000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $cargo = ConsultaCargo::query()->create([
                'consulta_id' => $consulta->id,
                'estado' => ConsultaCargo::ESTADO_CONFIRMADO,
                'moneda' => 'PEN',
                'subtotal_sin_igv' => '59.32',
                'igv_importe' => '10.68',
                'total' => '70.00',
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            $lineaServicio = ConsultaCargoLinea::query()->create([
                'consulta_cargo_id' => $cargo->id,
                'tipo_linea' => ConsultaCargoLinea::TIPO_SERVICIO,
                'producto_id' => null,
                'concepto' => 'Consulta general',
                'cantidad' => '1.0000',
                'precio_unitario' => '50.0000',
                'descuento_importe' => '0.00',
                'orden' => 0,
            ]);

            $lineaProducto = ConsultaCargoLinea::query()->create([
                'consulta_cargo_id' => $cargo->id,
                'tipo_linea' => ConsultaCargoLinea::TIPO_PRODUCTO,
                'producto_id' => $producto->id,
                'concepto' => $producto->nombre,
                'cantidad' => '1.0000',
                'precio_unitario' => '20.0000',
                'descuento_importe' => '0.00',
                'orden' => 1,
            ]);

            $sesion = CajaSesion::query()->create([
                'sede_id' => $sede->id,
                'estado' => CajaSesion::ESTADO_ABIERTA,
                'moneda' => 'PEN',
                'saldo_apertura' => '0.00',
                'opened_at' => now(),
                'opened_by_id' => $userId,
            ]);

            return [
                'propietario' => $prop,
                'paciente' => $paciente,
                'consulta' => $consulta,
                'cargo' => $cargo,
                'linea_servicio' => $lineaServicio,
                'linea_producto' => $lineaProducto,
                'producto' => $producto,
                'sede' => $sede,
                'sesion' => $sesion,
            ];
        });
    }

    private static function ensureClinicSettings(string $userId): void
    {
        if (ClinicSetting::query()->exists()) {
            return;
        }

        ClinicSetting::query()->create([
            'moneda' => 'PEN',
            'igv_porcentaje' => 18,
            'precio_incluye_igv' => true,
            'razon_social' => 'Clínica Test',
            'nombre_comercial' => 'Clínica Test',
            'updated_by_id' => $userId,
        ]);
    }

    /**
     * @param  array{
     *     linea_servicio: ConsultaCargoLinea,
     *     linea_producto: ConsultaCargoLinea,
     *     producto: Producto,
     * }  $scenario
     * @return array<string, mixed>
     */
    public static function ventaPayloadFromCargo(array $scenario): array
    {
        return [
            'caja_sesion_id' => $scenario['sesion']->id,
            'propietario_id' => $scenario['propietario']->id,
            'paciente_id' => $scenario['paciente']->id,
            'consulta_id' => $scenario['consulta']->id,
            'consulta_cargo_id' => $scenario['cargo']->id,
            'metodo_pago' => 'yape',
            'lineas' => [
                [
                    'tipo_linea' => ConsultaCargoLinea::TIPO_SERVICIO,
                    'concepto' => $scenario['linea_servicio']->concepto,
                    'cantidad' => '1.00',
                    'precio_lista' => '50.00',
                    'consulta_cargo_linea_id' => $scenario['linea_servicio']->id,
                ],
                [
                    'producto_id' => $scenario['producto']->id,
                    'tipo_linea' => ConsultaCargoLinea::TIPO_PRODUCTO,
                    'cantidad' => '1.00',
                    'precio_lista' => '20.00',
                    'consulta_cargo_linea_id' => $scenario['linea_producto']->id,
                ],
            ],
        ];
    }
}
