<?php

declare(strict_types=1);

namespace App\Services\ClinicBot;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Hotel\HotelCatalogoMode;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\HotelEstanciaTarifa;
use App\Models\HotelTipoEstancia;
use App\Models\Producto;
use App\Models\ServicioClinico;
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
     * @return list<array{id: string, nombre: string, precio: string, categoria: string|null, duracion_minutos: int|null}>
     */
    public function listClinicalServices(?string $search = null): array
    {
        $query = ServicioClinico::query()
            ->with('categoria:id,nombre')
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre');

        if ($search !== null && trim($search) !== '') {
            $term = '%'.mb_strtolower(trim($search)).'%';
            $query->where(function ($q) use ($term): void {
                $q->whereRaw('LOWER(nombre) LIKE ?', [$term])
                    ->orWhereHas('categoria', function ($categoria) use ($term): void {
                        $categoria->whereRaw('LOWER(nombre) LIKE ?', [$term]);
                    });
            });
        }

        return $query
            ->limit(self::PRODUCT_LIMIT)
            ->get()
            ->map(fn (ServicioClinico $servicio): array => [
                'id' => $servicio->id,
                'nombre' => $servicio->nombre,
                'precio' => number_format((float) $servicio->precio_lista, 2, '.', ''),
                'categoria' => $servicio->categoria?->nombre,
                'duracion_minutos' => $servicio->duracion_minutos,
            ])
            ->all();
    }

    /**
     * @return list<array{id: string, nombre: string, precio: string|null, duracion_minutos: int|null, tipo: string}>
     */
    public function listGroomingServices(?string $search = null): array
    {
        if (! $this->moduleEnabled('grooming')) {
            return [];
        }

        $items = [];
        if (GroomingCatalogoMode::usaCatalogoPersonalizado()) {
            $items = GroomingServicio::query()
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
        } else {
            $tarifas = GroomingServicioTarifa::query()
                ->get()
                ->keyBy('servicio');

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
        }

        return $this->filterBySearch($items, $search);
    }

    /**
     * @return list<array{id: string, nombre: string, precio: string|null, unidad: string, tipo: string}>
     */
    public function listHotelServices(?string $search = null): array
    {
        if (! $this->moduleEnabled('hotel')) {
            return [];
        }

        $items = [];
        if (HotelCatalogoMode::usaCatalogoPersonalizado()) {
            $items = HotelTipoEstancia::query()
                ->where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get()
                ->map(fn (HotelTipoEstancia $tipo): array => [
                    'id' => $tipo->id,
                    'nombre' => $tipo->nombre,
                    'precio' => number_format((float) $tipo->precio_lista, 2, '.', ''),
                    'unidad' => 'por noche',
                    'tipo' => 'personalizado',
                ])
                ->all();
        } else {
            $tarifas = HotelEstanciaTarifa::query()
                ->where('activo', true)
                ->get()
                ->keyBy('tipo_estancia');

            foreach (HotelCatalogoTipoEstancia::slugs() as $slug) {
                if ($slug === HotelCatalogoTipoEstancia::OTRO_PERSONALIZADO) {
                    continue;
                }

                $tarifa = $tarifas->get($slug);
                $items[] = [
                    'id' => $slug,
                    'nombre' => $this->legacyGroomingLabel($slug),
                    'precio' => $tarifa !== null ? number_format((float) $tarifa->precio_lista, 2, '.', '') : null,
                    'unidad' => 'por noche',
                    'tipo' => 'legacy',
                ];
            }
        }

        return $this->filterBySearch($items, $search);
    }

    public function buildPromptCatalogSummary(): string
    {
        $tenantId = tenant_id();
        if ($tenantId === null) {
            return '';
        }

        return Cache::remember("clinic_bot_catalog_summary_{$tenantId}", now()->addMinutes(5), function (): string {
            $blocks = [];

            $clinical = $this->listClinicalServices();
            if ($clinical !== []) {
                $lines = array_map(
                    fn (array $s): string => sprintf(
                        '- %s%s — S/ %s',
                        $s['nombre'],
                        $s['categoria'] ? " ({$s['categoria']})" : '',
                        $s['precio'],
                    ),
                    array_slice($clinical, 0, 25),
                );
                $extra = count($clinical) > 25
                    ? "\n(Hay más servicios clínicos; usa listar_servicios_clinicos para buscar.)"
                    : '';
                $blocks[] = "SERVICIOS CLÍNICOS (TARIFAS):\n".implode("\n", $lines).$extra;
            }

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

            $hotel = $this->listHotelServices();
            if ($hotel !== []) {
                $lines = array_map(
                    fn (array $s): string => sprintf(
                        '- %s%s — %s',
                        $s['nombre'],
                        $s['precio'] !== null ? ' — S/ '.$s['precio'] : '',
                        $s['unidad'],
                    ),
                    array_slice($hotel, 0, 20),
                );
                $blocks[] = "HOTEL / GUARDERÍA:\n".implode("\n", $lines);
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

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function filterBySearch(array $items, ?string $search): array
    {
        if ($search === null || trim($search) === '') {
            return $items;
        }

        $needle = mb_strtolower(trim($search));

        return array_values(array_filter(
            $items,
            static fn (array $item): bool => str_contains(mb_strtolower((string) ($item['nombre'] ?? '')), $needle),
        ));
    }

    private function legacyGroomingLabel(string $slug): string
    {
        return mb_convert_case(str_replace('_', ' ', $slug), MB_CASE_TITLE, 'UTF-8');
    }
}
