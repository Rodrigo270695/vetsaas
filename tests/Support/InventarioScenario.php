<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Models\ClinicSetting;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sede;
use App\Models\Tenant;
use App\Tenancy\Facades\Tenant as TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * @return array{
 *     sede: Sede,
 *     producto: Producto,
 *     proveedor: Proveedor,
 * }
 */
final class InventarioScenario
{
    public static function seed(Tenant $tenant, string $slug, string $userId, float $stockInicial = 10.0): array
    {
        $sede = Sede::query()->create([
            'tenant_id' => $tenant->id,
            'nombre' => 'Sede Inventario Test',
            'codigo' => 'INV-'.Str::upper(Str::random(4)),
            'activa' => true,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return TenantContext::runForSlug($slug, function () use ($userId, $sede, $stockInicial) {
            if (! ClinicSetting::query()->exists()) {
                ClinicSetting::query()->create([
                    'moneda' => 'PEN',
                    'igv_porcentaje' => 18,
                    'precio_incluye_igv' => true,
                    'razon_social' => 'Clínica Inventario Test',
                    'nombre_comercial' => 'Inventario Test',
                    'updated_by_id' => $userId,
                ]);
            }

            $producto = Producto::query()->create([
                'nombre' => 'Producto Kardex Test',
                'slug' => 'prod-inv-'.Str::lower(Str::random(6)),
                'sku' => 'SKU-INV-'.Str::upper(Str::random(4)),
                'precio_venta' => '25.00',
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            DB::table('existencias_sede')->insert([
                'id' => (string) Str::uuid(),
                'producto_id' => $producto->id,
                'sede_id' => $sede->id,
                'cantidad' => number_format($stockInicial, 3, '.', ''),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $proveedor = Proveedor::query()->create([
                'ruc' => '20'.Str::padLeft((string) random_int(0, 999999999), 9, '0'),
                'razon_social' => 'Proveedor Test SAC',
                'activo' => true,
                'created_by_id' => $userId,
                'updated_by_id' => $userId,
            ]);

            return [
                'sede' => $sede,
                'producto' => $producto,
                'proveedor' => $proveedor,
            ];
        });
    }

    public static function stockEnSede(string $productoId, string $sedeId): float
    {
        return (float) (string) DB::table('existencias_sede')
            ->where('producto_id', $productoId)
            ->where('sede_id', $sedeId)
            ->value('cantidad');
    }
}
