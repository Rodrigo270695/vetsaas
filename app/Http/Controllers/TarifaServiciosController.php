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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

    public function storeGrooming(GroomingServicioTarifaRequest $request): RedirectResponse
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

    public function updateGrooming(
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

    public function destroyGrooming(GroomingServicioTarifa $grooming_tarifa): RedirectResponse
    {
        abort_unless(request()->user()?->can('tarifas.delete'), 403);

        $grooming_tarifa->delete();

        return back()->with('success', __('tarifas-servicios.grooming.deleted'));
    }

    public function storeHotel(HotelEstanciaTarifaRequest $request): RedirectResponse
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

    public function updateHotel(
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

    public function destroyHotel(HotelEstanciaTarifa $hotel_tarifa): RedirectResponse
    {
        abort_unless(request()->user()?->can('tarifas.delete'), 403);

        $hotel_tarifa->delete();

        return back()->with('success', __('tarifas-servicios.hotel.deleted'));
    }
}
