<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Grooming\GroomingCatalogoServicio;
use App\Hotel\HotelCatalogoTipoEstancia;
use App\Http\Requests\GroomingServicioTarifaRequest;
use App\Http\Requests\HotelEstanciaTarifaRequest;
use App\Models\GroomingServicioTarifa;
use App\Models\HotelEstanciaTarifa;
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

        $groomingSearch = trim((string) $request->string('grooming_search', ''));
        $hotelSearch = trim((string) $request->string('hotel_search', ''));

        $groomingQuery = GroomingServicioTarifa::query()->orderBy('servicio');
        if ($groomingSearch !== '') {
            $groomingQuery->where('servicio', 'ILIKE', "%{$groomingSearch}%");
        }

        $hotelQuery = HotelEstanciaTarifa::query()->orderBy('tipo_estancia');
        if ($hotelSearch !== '') {
            $hotelQuery->where('tipo_estancia', 'ILIKE', "%{$hotelSearch}%");
        }

        return Inertia::render('configuracion/tarifas/index', [
            'tab' => $tab,
            'catalogoGrooming' => GroomingCatalogoServicio::grupos(),
            'catalogoHotel' => HotelCatalogoTipoEstancia::grupos(),
            'groomingTarifas' => $groomingQuery->paginate(self::PER_PAGE, ['*'], 'grooming_page')->withQueryString(),
            'hotelTarifas' => $hotelQuery->paginate(self::PER_PAGE, ['*'], 'hotel_page')->withQueryString(),
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
