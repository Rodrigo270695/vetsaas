<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Grooming\GroomingCatalogoMode;
use App\Grooming\GroomingCatalogoServicio;
use App\Hotel\HotelCatalogoMode;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Http\Requests\GroomingServicioTarifaRequest;
use App\Http\Requests\HotelEstanciaTarifaRequest;
use App\Models\GroomingServicio;
use App\Models\GroomingServicioTarifa;
use App\Models\HotelEstanciaTarifa;
use App\Models\HotelTipoEstancia;
use App\Support\Catalog\CatalogoClinicaValidator;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class TarifaServiciosController extends Controller
{
    private const PER_PAGE = 15;

    private const TABS = ['grooming', 'hotel'];

    public function index(Request $request): Response
    {
        $tab = (string) $request->string('tab', 'grooming');
        if (! in_array($tab, self::TABS, true)) {
            $tab = 'grooming';
        }

        $groomingPersonalizado = GroomingCatalogoMode::usaCatalogoPersonalizado();
        $hotelPersonalizado = HotelCatalogoMode::usaCatalogoPersonalizado();

        $groomingSearch = trim((string) $request->string('grooming_search', ''));
        $hotelSearch = trim((string) $request->string('hotel_search', ''));

        $groomingServicios = collect();
        $hotelTipos = collect();

        if ($groomingPersonalizado) {
            $groomingQuery = GroomingServicio::query()->orderBy('orden')->orderBy('nombre');
            if ($groomingSearch !== '') {
                $groomingQuery->where(function ($q) use ($groomingSearch): void {
                    $q->where('nombre', 'ILIKE', "%{$groomingSearch}%")
                        ->orWhere('categoria', 'ILIKE', "%{$groomingSearch}%")
                        ->orWhere('codigo_legacy', 'ILIKE', "%{$groomingSearch}%");
                });
            }
            $groomingServicios = $groomingQuery->get([
                'id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'duracion_minutos', 'activo', 'orden',
            ]);
        }

        if ($hotelPersonalizado) {
            $hotelQuery = HotelTipoEstancia::query()->orderBy('orden')->orderBy('nombre');
            if ($hotelSearch !== '') {
                $hotelQuery->where(function ($q) use ($hotelSearch): void {
                    $q->where('nombre', 'ILIKE', "%{$hotelSearch}%")
                        ->orWhere('categoria', 'ILIKE', "%{$hotelSearch}%")
                        ->orWhere('codigo_legacy', 'ILIKE', "%{$hotelSearch}%");
                });
            }
            $hotelTipos = $hotelQuery->get([
                'id', 'nombre', 'categoria', 'codigo_legacy', 'precio_lista', 'moneda', 'activo', 'orden',
            ]);
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

        try {
            GroomingServicio::query()->create([
                'nombre' => $data['nombre'],
                'categoria' => $data['categoria'] ?? null,
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

        try {
            $servicio->update([
                'nombre' => $data['nombre'],
                'categoria' => $data['categoria'] ?? null,
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

        try {
            HotelTipoEstancia::query()->create([
                'nombre' => $data['nombre'],
                'categoria' => $data['categoria'] ?? null,
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

        try {
            $tipo->update([
                'nombre' => $data['nombre'],
                'categoria' => $data['categoria'] ?? null,
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
