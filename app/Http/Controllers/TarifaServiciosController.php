<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Hotel\HotelCatalogoMode;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Http\Requests\GroomingServicioTarifaRequest;
use App\Http\Requests\HotelEstanciaTarifaRequest;
use App\Models\CategoriaGrooming;
use App\Models\CategoriaHotel;
use App\Models\CategoriaServicioClinico;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\HotelEstanciaTarifa;
use App\Models\HotelTipoEstancia;
use App\Models\ServicioClinico;
use App\Models\Tenant;
use App\Support\Catalog\CatalogoClinicaValidator;
use App\Support\Tenancy\TenantModuleAccess;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TarifaServiciosController extends Controller
{
    private const PER_PAGE = 15;

    private const TABS = ['grooming', 'hotel', 'clinica'];

    public function index(Request $request): Response
    {
        $tab = (string) $request->string('tab', 'clinica');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'clinica';
        }

        $tenant = tenant_id() !== null ? Tenant::query()->find(tenant_id()) : null;
        if (! TenantModuleAccess::isTarifasTabEnabled($tenant, $tab)) {
            if (TenantModuleAccess::isTarifasTabEnabled($tenant, 'grooming')) {
                $tab = 'grooming';
            } elseif (TenantModuleAccess::isTarifasTabEnabled($tenant, 'hotel')) {
                $tab = 'hotel';
            } else {
                $tab = 'clinica';
            }
        }

        $groomingPersonalizado = GroomingCatalogoMode::usaCatalogoPersonalizado();
        $hotelPersonalizado = HotelCatalogoMode::usaCatalogoPersonalizado();

        $groomingSearch = trim((string) $request->string('grooming_search', ''));
        $hotelSearch = trim((string) $request->string('hotel_search', ''));
        $clinicaSearch = trim((string) $request->string('clinica_search', ''));

        $groomingServicios = collect();
        $hotelTipos = collect();
        $serviciosClinicos = collect();
        $categoriaOptions = collect();
        $groomingCategoriaOptions = collect();
        $hotelCategoriaOptions = collect();

        if ($groomingPersonalizado) {
            $groomingSelect = [
                'id', 'nombre', 'categoria', 'precio_lista', 'moneda', 'duracion_minutos', 'activo', 'orden', 'codigo_legacy',
            ];
            if (Schema::hasColumn('grooming_servicios', 'categoria_id')) {
                $groomingSelect[] = 'categoria_id';
            }

            $groomingQuery = GroomingServicio::query()
                ->select($groomingSelect)
                ->orderBy('orden')
                ->orderBy('nombre');

            if (Schema::hasTable('grooming_servicio_insumo')) {
                $groomingQuery
                    ->withCount('insumos as insumos_count')
                    ->withSum('insumos as insumos_total', 'precio');
            }

            if ($groomingSearch !== '') {
                $groomingQuery->where(function ($q) use ($groomingSearch): void {
                    $q->where('nombre', 'ILIKE', "%{$groomingSearch}%")
                        ->orWhere('categoria', 'ILIKE', "%{$groomingSearch}%")
                        ->orWhere('codigo_legacy', 'ILIKE', "%{$groomingSearch}%");
                });
            }
            $groomingServicios = $groomingQuery->get();
        }

        if ($hotelPersonalizado) {
            $hotelSelect = [
                'id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'activo', 'orden',
            ];
            if (Schema::hasColumn('hotel_tipos_estancia', 'categoria_id')) {
                $hotelSelect[] = 'categoria_id';
            }

            $hotelQuery = HotelTipoEstancia::query()->orderBy('orden')->orderBy('nombre');
            if ($hotelSearch !== '') {
                $hotelQuery->where(function ($q) use ($hotelSearch): void {
                    $q->where('nombre', 'ILIKE', "%{$hotelSearch}%")
                        ->orWhere('categoria', 'ILIKE', "%{$hotelSearch}%")
                        ->orWhere('codigo_legacy', 'ILIKE', "%{$hotelSearch}%");
                });
            }
            $hotelTipos = $hotelQuery->get($hotelSelect);
        }

        if (Schema::hasTable('servicios_clinicos')) {
            $clinicaQuery = ServicioClinico::query()
                ->with('categoria:id,nombre')
                ->orderBy('orden')
                ->orderBy('nombre');

            if ($clinicaSearch !== '') {
                $clinicaQuery->where(function ($q) use ($clinicaSearch): void {
                    $q->where('nombre', 'ILIKE', "%{$clinicaSearch}%")
                        ->orWhereHas('categoria', function ($cat) use ($clinicaSearch): void {
                            $cat->where('nombre', 'ILIKE', "%{$clinicaSearch}%");
                        });
                });
            }

            $serviciosClinicos = $clinicaQuery->get()->map(static function (ServicioClinico $row): array {
                return [
                    'id' => $row->id,
                    'nombre' => $row->nombre,
                    'categoria' => $row->categoria?->nombre,
                    'categoria_id' => $row->categoria_id,
                    'codigo_legacy' => null,
                    'precio_lista' => (string) $row->precio_lista,
                    'precio_costo' => $row->precio_costo !== null ? (string) $row->precio_costo : null,
                    'moneda' => $row->moneda,
                    'duracion_minutos' => $row->duracion_minutos,
                    'activo' => $row->activo,
                    'orden' => $row->orden,
                ];
            });
        }

        if (Schema::hasTable('categorias_servicio_clinico')) {
            $categoriaOptions = CategoriaServicioClinico::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']);
        }

        if (Schema::hasTable('categorias_grooming')) {
            $groomingCategoriaOptions = CategoriaGrooming::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']);
        }

        if (Schema::hasTable('categorias_hotel')) {
            $hotelCategoriaOptions = CategoriaHotel::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre']);
        }

        $groomingTarifasQuery = GroomingServicioTarifa::query()->orderBy('servicio');
        if ($groomingSearch !== '' && ! $groomingPersonalizado) {
            $groomingTarifasQuery->where('servicio', 'ILIKE', "%{$groomingSearch}%");
        }

        $hotelTarifasQuery = HotelEstanciaTarifa::query()->orderBy('tipo_estancia');
        if ($hotelSearch !== '' && ! $hotelPersonalizado) {
            $hotelTarifasQuery->where('tipo_estancia', 'ILIKE', "%{$hotelSearch}%");
        }

        return Inertia::render('configuracion/tarifas/index', [
            'tab' => $tab,
            'grooming_catalogo_personalizado' => $groomingPersonalizado,
            'hotel_catalogo_personalizado' => $hotelPersonalizado,
            'groomingServicios' => $groomingServicios,
            'hotelTipos' => $hotelTipos,
            'serviciosClinicos' => $serviciosClinicos,
            'categoriaOptions' => $categoriaOptions,
            'groomingCategoriaOptions' => $groomingCategoriaOptions,
            'hotelCategoriaOptions' => $hotelCategoriaOptions,
            'catalogoGrooming' => $groomingPersonalizado ? [] : GroomingCatalogoServicio::grupos(),
            'catalogoHotel' => $hotelPersonalizado ? [] : HotelCatalogoTipoEstancia::grupos(),
            'groomingTarifas' => $groomingPersonalizado
                ? null
                : $groomingTarifasQuery->paginate(self::PER_PAGE, ['*'], 'grooming_page')->withQueryString(),
            'hotelTarifas' => $hotelPersonalizado
                ? null
                : $hotelTarifasQuery->paginate(self::PER_PAGE, ['*'], 'hotel_page')->withQueryString(),
            'filters' => [
                'grooming_search' => $groomingSearch,
                'hotel_search' => $hotelSearch,
                'clinica_search' => $clinicaSearch,
            ],
        ]);
    }

    public function storeGrooming(Request $request): RedirectResponse
    {
        if ($this->usesGroomingCatalogoPersonalizado($request)) {
            abort_unless(
                Schema::hasTable('grooming_servicios'),
                503,
                __('tarifas-servicios.grooming.missing_table'),
            );

            return $this->storeGroomingCatalogo($request);
        }

        abort_unless(
            Schema::hasTable('grooming_servicio_tarifas'),
            503,
            __('tarifas-servicios.grooming.missing_legacy_table'),
        );

        return $this->storeGroomingTarifaLegacy(
            $this->resolveFormRequest(GroomingServicioTarifaRequest::class, $request),
        );
    }

    public function updateGrooming(Request $request, string $grooming_tarifa): RedirectResponse
    {
        $servicio = GroomingServicio::query()->find($grooming_tarifa);
        if ($servicio !== null) {
            abort_unless(
                Schema::hasTable('grooming_servicios'),
                503,
                __('tarifas-servicios.grooming.missing_table'),
            );

            return $this->updateGroomingCatalogo($request, $servicio);
        }

        abort_unless(
            Schema::hasTable('grooming_servicio_tarifas'),
            503,
            __('tarifas-servicios.grooming.missing_legacy_table'),
        );

        return $this->updateGroomingTarifaLegacy(
            $this->resolveFormRequest(GroomingServicioTarifaRequest::class, $request),
            GroomingServicioTarifa::query()->findOrFail($grooming_tarifa),
        );
    }

    public function destroyGrooming(Request $request, string $grooming_tarifa): RedirectResponse
    {
        $servicio = GroomingServicio::query()->find($grooming_tarifa);
        if ($servicio !== null) {
            return app(GroomingServicioController::class)->destroy($request, $servicio);
        }

        abort_unless($request->user()?->can('tarifas.delete'), 403);

        GroomingServicioTarifa::query()->findOrFail($grooming_tarifa)->delete();

        return back()->with('success', __('tarifas-servicios.grooming.deleted'));
    }

    public function storeHotel(Request $request): RedirectResponse
    {
        if ($this->usesHotelCatalogoPersonalizado($request)) {
            abort_unless(
                Schema::hasTable('hotel_tipos_estancia'),
                503,
                __('tarifas-servicios.hotel.missing_table'),
            );

            return $this->storeHotelCatalogo($request);
        }

        abort_unless(
            Schema::hasTable('hotel_estancia_tarifas'),
            503,
            __('tarifas-servicios.hotel.missing_legacy_table'),
        );

        return $this->storeHotelTarifaLegacy(
            $this->resolveFormRequest(HotelEstanciaTarifaRequest::class, $request),
        );
    }

    public function updateHotel(Request $request, string $hotel_tarifa): RedirectResponse
    {
        $tipo = HotelTipoEstancia::query()->find($hotel_tarifa);
        if ($tipo !== null) {
            abort_unless(
                Schema::hasTable('hotel_tipos_estancia'),
                503,
                __('tarifas-servicios.hotel.missing_table'),
            );

            return $this->updateHotelCatalogo($request, $tipo);
        }

        abort_unless(
            Schema::hasTable('hotel_estancia_tarifas'),
            503,
            __('tarifas-servicios.hotel.missing_legacy_table'),
        );

        return $this->updateHotelTarifaLegacy(
            $this->resolveFormRequest(HotelEstanciaTarifaRequest::class, $request),
            HotelEstanciaTarifa::query()->findOrFail($hotel_tarifa),
        );
    }

    public function destroyHotel(Request $request, string $hotel_tarifa): RedirectResponse
    {
        $tipo = HotelTipoEstancia::query()->find($hotel_tarifa);
        if ($tipo !== null) {
            return app(HotelTipoEstanciaController::class)->destroy($request, $tipo);
        }

        abort_unless($request->user()?->can('tarifas.delete'), 403);

        HotelEstanciaTarifa::query()->findOrFail($hotel_tarifa)->delete();

        return back()->with('success', __('tarifas-servicios.hotel.deleted'));
    }

    public function storeClinica(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('tarifas.create'), 403);
        abort_unless(
            Schema::hasTable('servicios_clinicos'),
            503,
            __('tarifas-servicios.clinica.missing_table'),
        );

        $data = CatalogoClinicaValidator::clinica($request);
        $maxOrden = (int) ServicioClinico::query()->max('orden');

        try {
            ServicioClinico::query()->create([
                'nombre' => $data['nombre'],
                'categoria_id' => $data['categoria_id'] ?? null,
                'precio_lista' => $data['precio_lista'],
                'precio_costo' => $data['precio_costo'] ?? null,
                'moneda' => $data['moneda'] ?? 'PEN',
                'duracion_minutos' => $data['duracion_minutos'] ?? null,
                'activo' => $data['activo'] ?? true,
                'orden' => isset($data['orden']) ? (int) $data['orden'] : ($maxOrden + 1),
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['nombre' => __('tarifas-servicios.clinica.save_failed')]);
        }

        return back()->with('success', __('tarifas-servicios.clinica.created'));
    }

    public function updateClinica(Request $request, ServicioClinico $servicioClinico): RedirectResponse
    {
        abort_unless($request->user()?->can('tarifas.update'), 403);

        $data = CatalogoClinicaValidator::clinica($request);

        try {
            $servicioClinico->update([
                'nombre' => $data['nombre'],
                'categoria_id' => $data['categoria_id'] ?? null,
                'precio_lista' => $data['precio_lista'],
                'precio_costo' => array_key_exists('precio_costo', $data)
                    ? $data['precio_costo']
                    : $servicioClinico->precio_costo,
                'moneda' => $data['moneda'] ?? $servicioClinico->moneda,
                'duracion_minutos' => array_key_exists('duracion_minutos', $data)
                    ? $data['duracion_minutos']
                    : $servicioClinico->duracion_minutos,
                'activo' => $data['activo'] ?? $servicioClinico->activo,
                'orden' => isset($data['orden']) ? (int) $data['orden'] : $servicioClinico->orden,
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['nombre' => __('tarifas-servicios.clinica.save_failed')]);
        }

        return back()->with('success', __('tarifas-servicios.clinica.updated'));
    }

    public function destroyClinica(Request $request, ServicioClinico $servicioClinico): RedirectResponse
    {
        abort_unless($request->user()?->can('tarifas.delete'), 403);

        $servicioClinico->delete();

        return back()->with('success', __('tarifas-servicios.clinica.deleted'));
    }

    public function storeCategoriaClinica(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->can('tarifas.create') || $request->user()?->can('tarifas.update'),
            403,
        );
        abort_unless(
            Schema::hasTable('categorias_servicio_clinico'),
            503,
            __('tarifas-servicios.clinica.missing_categorias_table'),
        );

        $data = $request->validate([
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:80',
                Rule::unique('categorias_servicio_clinico', 'nombre')->whereNull('deleted_at'),
            ],
        ]);

        $nombre = trim((string) $data['nombre']);

        CategoriaServicioClinico::query()->create([
            'nombre' => $nombre,
            'activo' => true,
        ]);

        return back()->with('success', __('tarifas-servicios.clinica.categoria_created'));
    }

    public function storeCategoriaGrooming(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->can('tarifas.create') || $request->user()?->can('tarifas.update'),
            403,
        );
        abort_unless(
            Schema::hasTable('categorias_grooming'),
            503,
            __('tarifas-servicios.grooming.missing_categorias_table'),
        );

        $data = $request->validate([
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:80',
                Rule::unique('categorias_grooming', 'nombre')->whereNull('deleted_at'),
            ],
        ]);

        CategoriaGrooming::query()->create([
            'nombre' => trim((string) $data['nombre']),
            'activo' => true,
        ]);

        return back()->with('success', __('tarifas-servicios.grooming.categoria_created'));
    }

    public function storeCategoriaHotel(Request $request): RedirectResponse
    {
        abort_unless(
            $request->user()?->can('tarifas.create') || $request->user()?->can('tarifas.update'),
            403,
        );
        abort_unless(
            Schema::hasTable('categorias_hotel'),
            503,
            __('tarifas-servicios.hotel.missing_categorias_table'),
        );

        $data = $request->validate([
            'nombre' => [
                'required',
                'string',
                'min:2',
                'max:80',
                Rule::unique('categorias_hotel', 'nombre')->whereNull('deleted_at'),
            ],
        ]);

        CategoriaHotel::query()->create([
            'nombre' => trim((string) $data['nombre']),
            'activo' => true,
        ]);

        return back()->with('success', __('tarifas-servicios.hotel.categoria_created'));
    }

    private function storeGroomingTarifaLegacy(GroomingServicioTarifaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        GroomingServicioTarifa::query()->create([
            'servicio' => $data['servicio'],
            'precio_lista' => $data['precio_lista'],
            'moneda' => $data['moneda'] ?? 'PEN',
            'activo' => $data['activo'] ?? true,
        ]);

        return back()->with('success', __('tarifas-servicios.grooming.created'));
    }

    private function updateGroomingTarifaLegacy(
        GroomingServicioTarifaRequest $request,
        GroomingServicioTarifa $grooming_tarifa,
    ): RedirectResponse {
        $data = $request->validated();

        $grooming_tarifa->update([
            'servicio' => $data['servicio'],
            'precio_lista' => $data['precio_lista'],
            'moneda' => $data['moneda'] ?? 'PEN',
            'activo' => $data['activo'] ?? $grooming_tarifa->activo,
        ]);

        return back()->with('success', __('tarifas-servicios.grooming.updated'));
    }

    private function storeHotelTarifaLegacy(HotelEstanciaTarifaRequest $request): RedirectResponse
    {
        $data = $request->validated();

        HotelEstanciaTarifa::query()->create([
            'tipo_estancia' => $data['tipo_estancia'],
            'precio_lista' => $data['precio_lista'],
            'moneda' => $data['moneda'] ?? 'PEN',
            'activo' => $data['activo'] ?? true,
        ]);

        return back()->with('success', __('tarifas-servicios.hotel.created'));
    }

    private function updateHotelTarifaLegacy(
        HotelEstanciaTarifaRequest $request,
        HotelEstanciaTarifa $hotel_tarifa,
    ): RedirectResponse {
        $data = $request->validated();

        $hotel_tarifa->update([
            'tipo_estancia' => $data['tipo_estancia'],
            'precio_lista' => $data['precio_lista'],
            'moneda' => $data['moneda'] ?? 'PEN',
            'activo' => $data['activo'] ?? $hotel_tarifa->activo,
        ]);

        return back()->with('success', __('tarifas-servicios.hotel.updated'));
    }

    private function storeGroomingCatalogo(Request $request): RedirectResponse
    {
        $data = CatalogoClinicaValidator::grooming($request);
        $maxOrden = (int) GroomingServicio::query()->max('orden');
        $categoria = $this->resolveCategoriaGrooming($data);

        try {
            GroomingServicio::query()->create([
                'nombre' => $data['nombre'],
                'categoria' => $categoria['categoria'],
                'categoria_id' => $categoria['categoria_id'],
                'precio_lista' => $data['precio_lista'],
                'moneda' => $data['moneda'] ?? 'PEN',
                'duracion_minutos' => (int) ($data['duracion_minutos'] ?? 60),
                'activo' => $data['activo'] ?? true,
                'orden' => isset($data['orden']) ? (int) $data['orden'] : ($maxOrden + 1),
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['nombre' => __('tarifas-servicios.grooming.save_failed')]);
        }

        return back()->with('success', __('tarifas-servicios.grooming.created'));
    }

    private function updateGroomingCatalogo(Request $request, GroomingServicio $servicio): RedirectResponse
    {
        $data = CatalogoClinicaValidator::grooming($request);
        $categoria = $this->resolveCategoriaGrooming($data);

        try {
            $servicio->update([
                'nombre' => $data['nombre'],
                'categoria' => $categoria['categoria'],
                'categoria_id' => $categoria['categoria_id'],
                'precio_lista' => $data['precio_lista'],
                'moneda' => $data['moneda'] ?? $servicio->moneda,
                'duracion_minutos' => (int) ($data['duracion_minutos'] ?? $servicio->duracion_minutos ?? 60),
                'activo' => $data['activo'] ?? $servicio->activo,
                'orden' => isset($data['orden']) ? (int) $data['orden'] : $servicio->orden,
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['nombre' => __('tarifas-servicios.grooming.save_failed')]);
        }

        return back()->with('success', __('tarifas-servicios.grooming.updated'));
    }

    private function storeHotelCatalogo(Request $request): RedirectResponse
    {
        $data = CatalogoClinicaValidator::hotel($request);
        $maxOrden = (int) HotelTipoEstancia::query()->max('orden');
        $categoria = $this->resolveCategoriaHotel($data);

        try {
            HotelTipoEstancia::query()->create([
                'nombre' => $data['nombre'],
                'categoria' => $categoria['categoria'],
                'categoria_id' => $categoria['categoria_id'],
                'precio_lista' => $data['precio_lista'],
                'moneda' => $data['moneda'] ?? 'PEN',
                'activo' => $data['activo'] ?? true,
                'orden' => isset($data['orden']) ? (int) $data['orden'] : ($maxOrden + 1),
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['nombre' => __('tarifas-servicios.hotel.save_failed')]);
        }

        return back()->with('success', __('tarifas-servicios.hotel.created'));
    }

    private function updateHotelCatalogo(Request $request, HotelTipoEstancia $tipo): RedirectResponse
    {
        $data = CatalogoClinicaValidator::hotel($request);
        $categoria = $this->resolveCategoriaHotel($data);

        try {
            $tipo->update([
                'nombre' => $data['nombre'],
                'categoria' => $categoria['categoria'],
                'categoria_id' => $categoria['categoria_id'],
                'precio_lista' => $data['precio_lista'],
                'moneda' => $data['moneda'] ?? $tipo->moneda,
                'activo' => $data['activo'] ?? $tipo->activo,
                'orden' => isset($data['orden']) ? (int) $data['orden'] : $tipo->orden,
            ]);
        } catch (QueryException $exception) {
            report($exception);

            return back()
                ->withInput()
                ->withErrors(['nombre' => __('tarifas-servicios.hotel.save_failed')]);
        }

        return back()->with('success', __('tarifas-servicios.hotel.updated'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{categoria_id: ?string, categoria: ?string}
     */
    private function resolveCategoriaGrooming(array $data): array
    {
        $categoriaId = isset($data['categoria_id']) && is_string($data['categoria_id']) && $data['categoria_id'] !== ''
            ? $data['categoria_id']
            : null;

        if ($categoriaId !== null && Schema::hasTable('categorias_grooming')) {
            $nombre = CategoriaGrooming::query()->whereKey($categoriaId)->value('nombre');

            return [
                'categoria_id' => $categoriaId,
                'categoria' => is_string($nombre) ? $nombre : null,
            ];
        }

        $legacy = isset($data['categoria']) && is_string($data['categoria']) ? trim($data['categoria']) : '';

        return [
            'categoria_id' => null,
            'categoria' => $legacy === '' ? null : $legacy,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{categoria_id: ?string, categoria: ?string}
     */
    private function resolveCategoriaHotel(array $data): array
    {
        $categoriaId = isset($data['categoria_id']) && is_string($data['categoria_id']) && $data['categoria_id'] !== ''
            ? $data['categoria_id']
            : null;

        if ($categoriaId !== null && Schema::hasTable('categorias_hotel')) {
            $nombre = CategoriaHotel::query()->whereKey($categoriaId)->value('nombre');

            return [
                'categoria_id' => $categoriaId,
                'categoria' => is_string($nombre) ? $nombre : null,
            ];
        }

        $legacy = isset($data['categoria']) && is_string($data['categoria']) ? trim($data['categoria']) : '';

        return [
            'categoria_id' => null,
            'categoria' => $legacy === '' ? null : $legacy,
        ];
    }

    /**
     * @param  class-string<FormRequest>  $formRequestClass
     */
    private function resolveFormRequest(string $formRequestClass, Request $request): FormRequest
    {
        /** @var FormRequest $formRequest */
        $formRequest = $formRequestClass::createFrom($request);
        $formRequest->setContainer(app());
        $formRequest->setRedirector(app('redirect'));
        $formRequest->setRouteResolver(fn () => $request->route());

        if ($request->hasSession()) {
            $formRequest->setSession($request->session());
        }

        $formRequest->validateResolved();

        return $formRequest;
    }

    private function usesGroomingCatalogoPersonalizado(Request $request): bool
    {
        return GroomingCatalogoMode::usaCatalogoPersonalizado() || $request->filled('nombre');
    }

    private function usesHotelCatalogoPersonalizado(Request $request): bool
    {
        return HotelCatalogoMode::usaCatalogoPersonalizado() || $request->filled('nombre');
    }
}
