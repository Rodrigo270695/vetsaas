<?php

declare(strict_types=1);

namespace App\Services\ClinicBot;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\Producto;
use App\Models\Tenant;
use App\Support\Tenancy\TenantModuleAccess;
use Illuminate\Support\Facades\Cache;

final class ClinicBotCatalogService
{
    private const PRODUCT_LIMIT = 80;

    /**
     * @return list<array{id: string, nombre: string, precio: string, unidad: string|null, categoria: string|null}>
     */
    public function listProducts(?string $search = null): array
    {
        if (! $this->moduleEnabled('productos')) {
            return [];
        }

        $query = Producto::query()
            ->with('categoria:id,nombre')
            ->where('activo', true)
            ->orderBy('nombre');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.mb_strtolower(trim($search)).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(nombre) LIKE ?', [$term])
                    ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$term]);
            });
        }

        return $query
            ->limit(self::PRODUCT_LIMIT)
            ->get()
            ->map(fn (Producto $producto): array => [
                'id' => $producto->id,
                'nombre' => $producto->nombre,
                'precio' => number_format((float) $producto->precio_venta, 2, '.', ''),
                'unidad' => $producto->unidad,
                'categoria' => $producto->categoria?->nombre,
            ])
            ->all();
    }

    /**
     * @return list<array{id: string, nombre: string, precio: string|null, duracion_minutos: int|null, tipo: string}>
     */
    public function listGroomingServices(): array
    {
        if (! $this->moduleEnabled('grooming')) {
            return [];
        }

        if (GroomingCatalogoMode::usaCatalogoPersonalizado()) {
            return GroomingServicio::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get()
                ->map(fn (GroomingServicio $servicio): array => [
                    'id' => $servicio->id,
                    'nombre' => $servicio->nombre,
                    'precio' => number_format((float) $servicio->precio_lista, 2, '.', ''),
                    'duracion_minutos' => $servicio->duracion_minutos,
                    'tipo' => 'personalizado',
                ])
                ->all();
        }

        $tarifas = GroomingServicioTarifa::query()
            ->get()
            ->keyBy('servicio');

        $items = [];
        foreach (GroomingCatalogoServicio::slugs() as $slug) {
            if ($slug === GroomingCatalogoServicio::OTRO_PERSONALIZADO) {
                continue;
            }

            $tarifa = $tarifas->get($slug);
            $items[] = [
                'id' => $slug,
                'nombre' => $this->legacyGroomingLabel($slug),
                'precio' => $tarifa !== null ? number_format((float) $tarifa->precio_lista, 2, '.', '') : null,
                'duracion_minutos' => GroomingCatalogoServicio::duracionSugeridaPara($slug),
                'tipo' => 'legacy',
            ];
        }

        return $items;
    }

    public function buildPromptCatalogSummary(): string
    {
        $tenantId = tenant_id();
        if ($tenantId === null) {
            return '';
        }

        return Cache::remember("clinic_bot_catalog_summary_{$tenantId}", now()->addMinutes(5), function (): string {
            $blocks = [];

            $products = $this->listProducts();
            if ($products !== []) {
                $lines = array_map(
                    fn (array $p): string => sprintf(
                        '- %s%s — S/ %s',
                        $p['nombre'],
                        $p['categoria'] ? " ({$p['categoria']})" : '',
                        $p['precio'],
                    ),
                    array_slice($products, 0, 25),
                );
                $extra = count($products) > 25
                    ? "\n(Hay más productos; usa la herramienta listar_productos para buscar.)"
                    : '';
                $blocks[] = "PRODUCTOS EN INVENTARIO:\n".implode("\n", $lines).$extra;
            }

            $grooming = $this->listGroomingServices();
            if ($grooming !== []) {
                $lines = array_map(
                    fn (array $s): string => sprintf(
                        '- [%s] %s%s — %s min%s',
                        $s['id'],
                        $s['nombre'],
                        $s['precio'] !== null ? ' — S/ '.$s['precio'] : '',
                        $s['duracion_minutos'] ?? '?',
                        '',
                    ),
                    array_slice($grooming, 0, 30),
                );
                $blocks[] = "SERVICIOS DE GROOMING:\n".implode("\n", $lines);
            }

            return implode("\n\n", $blocks);
        });
    }

    public static function flushCache(?string $tenantId = null): void
    {
        $tenantId ??= tenant_id();
        if ($tenantId !== null) {
            Cache::forget("clinic_bot_catalog_summary_{$tenantId}");
        }
    }

    private function moduleEnabled(string $module): bool
    {
        $tenantId = tenant_id();
        if ($tenantId === null) {
            return true;
        }

        $tenant = Tenant::query()->find($tenantId);

        return TenantModuleAccess::isEnabled($tenant, $module);
    }

    private function legacyGroomingLabel(string $slug): string
    {
        return mb_convert_case(str_replace('_', ' ', $slug), MB_CASE_TITLE, 'UTF-8');
    }
}
