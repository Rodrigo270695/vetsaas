<?php

declare(strict_types=1);

namespace App\Services\InAppAssistant;

use App\Models\Cita;
use App\Models\ExistenciaSede;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\Propietario;
use App\Models\Venta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

final class InAppAssistantToolExecutor
{
    /**
     * @param  array<string, mixed>  $args
     */
    public function execute(string $name, array $args): string
    {
        $result = match ($name) {
            'buscar_pacientes' => $this->buscarPacientes((string) ($args['q'] ?? '')),
            'buscar_propietarios' => $this->buscarPropietarios((string) ($args['q'] ?? '')),
            'buscar_productos' => $this->buscarProductos((string) ($args['q'] ?? '')),
            'resumen_operativo' => $this->resumenOperativo(),
            default => ['ok' => false, 'error' => 'Herramienta no disponible.'],
        };

        return (string) json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarPacientes(string $q): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return ['ok' => false, 'error' => 'Indica al menos 2 caracteres para buscar.'];
        }

        if (! Schema::hasTable('pacientes')) {
            return ['ok' => false, 'error' => 'Módulo de pacientes no disponible.'];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = Paciente::query()
            ->with('propietario:id,nombres,apellidos,razon_social,telefono')
            ->where(function ($query) use ($like): void {
                $query->where('nombre', 'ILIKE', $like)
                    ->orWhere('microchip', 'ILIKE', $like)
                    ->orWhereHas('propietario', function ($p) use ($like): void {
                        $p->where('nombres', 'ILIKE', $like)
                            ->orWhere('apellidos', 'ILIKE', $like)
                            ->orWhere('razon_social', 'ILIKE', $like)
                            ->orWhere('telefono', 'ILIKE', $like);
                    });
            })
            ->orderBy('nombre')
            ->limit(8)
            ->get(['id', 'nombre', 'especie', 'raza', 'propietario_id', 'activo']);

        return [
            'ok' => true,
            'count' => $rows->count(),
            'pacientes' => $rows->map(static function (Paciente $p): array {
                $titular = $p->propietario?->razon_social
                    ?: trim(implode(' ', array_filter([(string) $p->propietario?->nombres, (string) $p->propietario?->apellidos])));

                return [
                    'nombre' => $p->nombre,
                    'especie' => $p->especie,
                    'raza' => $p->raza,
                    'activo' => $p->activo,
                    'titular' => $titular !== '' ? $titular : null,
                    'telefono_titular' => $p->propietario?->telefono,
                ];
            })->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarPropietarios(string $q): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return ['ok' => false, 'error' => 'Indica al menos 2 caracteres para buscar.'];
        }

        if (! Schema::hasTable('propietarios')) {
            return ['ok' => false, 'error' => 'Módulo de propietarios no disponible.'];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = Propietario::query()
            ->where(function ($query) use ($like): void {
                $query->where('nombres', 'ILIKE', $like)
                    ->orWhere('apellidos', 'ILIKE', $like)
                    ->orWhere('razon_social', 'ILIKE', $like)
                    ->orWhere('documento', 'ILIKE', $like)
                    ->orWhere('telefono', 'ILIKE', $like)
                    ->orWhere('email', 'ILIKE', $like);
            })
            ->orderBy('nombres')
            ->limit(8)
            ->get(['id', 'nombres', 'apellidos', 'razon_social', 'telefono', 'documento']);

        return [
            'ok' => true,
            'count' => $rows->count(),
            'propietarios' => $rows->map(static fn (Propietario $p): array => [
                'nombre' => $p->razon_social
                    ?: trim(implode(' ', array_filter([(string) $p->nombres, (string) $p->apellidos]))),
                'telefono' => $p->telefono,
                'documento' => $p->documento,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buscarProductos(string $q): array
    {
        $q = trim($q);
        if ($q === '' || mb_strlen($q) < 2) {
            return ['ok' => false, 'error' => 'Indica al menos 2 caracteres para buscar.'];
        }

        if (! Schema::hasTable('productos')) {
            return ['ok' => false, 'error' => 'Inventario no disponible.'];
        }

        $like = '%'.addcslashes($q, '%_\\').'%';
        $rows = Producto::query()
            ->where(function ($query) use ($like): void {
                $query->where('nombre', 'ILIKE', $like)
                    ->orWhere('sku', 'ILIKE', $like)
                    ->orWhere('slug', 'ILIKE', $like);
            })
            ->orderBy('nombre')
            ->limit(8)
            ->get(['id', 'nombre', 'sku', 'precio_venta', 'unidad', 'activo']);

        return [
            'ok' => true,
            'count' => $rows->count(),
            'productos' => $rows->map(static fn (Producto $p): array => [
                'nombre' => $p->nombre,
                'sku' => $p->sku,
                'precio_venta' => $p->precio_venta !== null ? (string) $p->precio_venta : null,
                'unidad' => $p->unidad,
                'activo' => $p->activo,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumenOperativo(): array
    {
        $tz = (string) config('app.timezone', 'America/Lima');
        $hoy = Carbon::now($tz)->toDateString();
        $out = [
            'ok' => true,
            'fecha' => $hoy,
            'zona_horaria' => $tz,
        ];

        if (Schema::hasTable('citas')) {
            $out['citas_hoy'] = Cita::query()
                ->whereDate('inicio_at', $hoy)
                ->count();
        }

        if (Schema::hasTable('ventas')) {
            $ventasHoy = Venta::query()
                ->where(function ($q) use ($hoy): void {
                    $q->whereDate('fecha_pago', $hoy)
                        ->orWhere(function ($inner) use ($hoy): void {
                            $inner->whereNull('fecha_pago')->whereDate('created_at', $hoy);
                        });
                })
                ->whereNull('anulado_at');

            $out['ventas_hoy'] = [
                'cantidad' => (clone $ventasHoy)->count(),
                'total' => (string) ((clone $ventasHoy)->sum('total') ?? 0),
            ];
        }

        if (Schema::hasTable('existencias_sede') && Schema::hasTable('productos')) {
            $alertas = ExistenciaSede::query()
                ->with('producto:id,nombre,sku,stock_minimo')
                ->whereHas('producto', fn ($q) => $q->where('activo', true)->where('stock_minimo', '>', 0))
                ->get()
                ->filter(function (ExistenciaSede $e): bool {
                    $min = (float) ($e->producto?->stock_minimo ?? 0);

                    return $min > 0 && (float) $e->cantidad <= $min;
                })
                ->take(8)
                ->map(static fn (ExistenciaSede $e): array => [
                    'producto' => $e->producto?->nombre,
                    'sku' => $e->producto?->sku,
                    'cantidad' => (string) $e->cantidad,
                    'stock_minimo' => (string) ($e->producto?->stock_minimo ?? 0),
                ])
                ->values()
                ->all();

            $out['stock_bajo'] = [
                'count' => count($alertas),
                'items' => $alertas,
            ];
        }

        if (Schema::hasTable('pacientes')) {
            $out['pacientes_activos'] = Paciente::query()->where('activo', true)->count();
        }

        return $out;
    }
}
