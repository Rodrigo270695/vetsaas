<?php

declare(strict_types=1);

namespace App\Services\Offline;

use App\Models\CajaSesion;
use App\Models\ClinicSetting;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Sede;
use App\Models\Tenant;
use App\Models\User;
use App\Support\PlanCapabilities;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class OfflineBootstrapService
{
    /**
     * @return array<string, mixed>
     */
    public function caja(User $user, ?Tenant $tenant): array
    {
        $miSesion = CajaSesion::query()
            ->where('estado', CajaSesion::ESTADO_ABIERTA)
            ->where('opened_by_id', $user->id)
            ->first();

        $sedeNombre = null;
        $sedeId = $miSesion?->sede_id;
        if ($miSesion !== null) {
            $sedeNombre = Sede::query()->whereKey($miSesion->sede_id)->value('nombre');
        }

        $clinic = ClinicSetting::current();

        $propietarios = Propietario::query()
            ->where('activo', true)
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'numero_documento'])
            ->map(fn (Propietario $pr): array => [
                'id' => $pr->id,
                'label' => $pr->razon_social ?: trim(implode(' ', array_filter([$pr->nombres, $pr->apellidos]))),
                'doc' => $pr->numero_documento,
            ])
            ->values()
            ->all();

        $productos = $this->productosParaSede($sedeId);

        $pacientes = Paciente::query()
            ->where('activo', true)
            ->whereIn('propietario_id', collect($propietarios)->pluck('id'))
            ->orderBy('nombre')
            ->limit(500)
            ->get(['id', 'nombre', 'propietario_id'])
            ->map(fn (Paciente $p): array => [
                'id' => $p->id,
                'nombre' => $p->nombre,
                'propietario_id' => $p->propietario_id,
            ])
            ->values()
            ->all();

        return [
            'cached_at' => now()->toIso8601String(),
            'puede_vender' => $miSesion !== null,
            'mi_sesion' => $miSesion === null ? null : [
                'id' => $miSesion->id,
                'sede_id' => $miSesion->sede_id,
                'sede_nombre' => $sedeNombre ?? '—',
                'moneda' => $miSesion->moneda,
            ],
            'clinica' => [
                'moneda' => $clinic->moneda,
                'igv_porcentaje' => (string) $clinic->igv_porcentaje,
                'precio_incluye_igv' => (bool) $clinic->precio_incluye_igv,
                'emite_comprobantes_sunat' => (bool) $clinic->emite_comprobantes_sunat,
                'plan_permite_boletas' => PlanCapabilities::boletasElectronicas($tenant),
                'plan_permite_facturas' => PlanCapabilities::facturasElectronicas($tenant),
            ],
            'propietarios_opciones' => $propietarios,
            'productos' => $productos,
            'pacientes' => $pacientes,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function productosParaSede(?string $sedeId): array
    {
        $query = Producto::query()
            ->where('productos.activo', true)
            ->whereNull('productos.deleted_at');

        if ($sedeId !== null) {
            $rows = (clone $query)
                ->leftJoin('existencias_sede as es', function ($join) use ($sedeId): void {
                    $join->on('es.producto_id', '=', 'productos.id')
                        ->where('es.sede_id', '=', $sedeId);
                })
                ->orderBy('productos.nombre')
                ->limit(400)
                ->get([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'productos.codigo_barras',
                    'productos.precio_venta',
                    'productos.unidad',
                    DB::raw('COALESCE(es.cantidad, 0) as stock_sede'),
                ]);
        } else {
            $rows = $query
                ->orderBy('productos.nombre')
                ->limit(400)
                ->get([
                    'productos.id',
                    'productos.nombre',
                    'productos.sku',
                    'productos.codigo_barras',
                    'productos.precio_venta',
                    'productos.unidad',
                ]);

            foreach ($rows as $p) {
                $p->setAttribute('stock_sede', '0');
            }
        }

        return $rows->map(fn (Producto $p): array => [
            'id' => $p->id,
            'nombre' => $p->nombre,
            'sku' => $p->sku,
            'codigo_barras' => $p->codigo_barras,
            'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
            'unidad' => $p->unidad,
            'stock_sede' => (string) ($p->stock_sede ?? '0'),
        ])->values()->all();
    }
}
