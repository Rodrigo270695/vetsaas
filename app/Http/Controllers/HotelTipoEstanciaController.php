<?php

namespace App\Http\Controllers;

use App\Hotel\HotelCatalogoMode;
use App\Http\Requests\HotelTipoEstanciaRequest;
use App\Models\HotelTipoEstancia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class HotelTipoEstanciaController extends Controller
{
    public function store(HotelTipoEstanciaRequest $request): RedirectResponse
    {
        abort_unless(HotelCatalogoMode::usaCatalogoPersonalizado(), 404);

        $data = $request->validated();
        $maxOrden = (int) HotelTipoEstancia::query()->max('orden');

        HotelTipoEstancia::query()->create([
            'nombre' => $data['nombre'],
            'categoria' => $data['categoria'] ?? null,
            'precio_lista' => $data['precio_lista'],
            'moneda' => $data['moneda'] ?? 'PEN',
            'activo' => $data['activo'] ?? true,
            'orden' => isset($data['orden']) ? (int) $data['orden'] : ($maxOrden + 1),
        ]);

        return back()->with('success', __('hotel.tipos.flash.created'));
    }

    public function update(HotelTipoEstanciaRequest $request, HotelTipoEstancia $hotelTipoEstancia): RedirectResponse
    {
        abort_unless(HotelCatalogoMode::usaCatalogoPersonalizado(), 404);

        $data = $request->validated();

        $hotelTipoEstancia->update([
            'nombre' => $data['nombre'],
            'categoria' => $data['categoria'] ?? null,
            'precio_lista' => $data['precio_lista'],
            'moneda' => $data['moneda'] ?? $hotelTipoEstancia->moneda,
            'activo' => $data['activo'] ?? $hotelTipoEstancia->activo,
            'orden' => isset($data['orden']) ? (int) $data['orden'] : $hotelTipoEstancia->orden,
        ]);

        return back()->with('success', __('hotel.tipos.flash.updated'));
    }

    public function destroy(Request $request, HotelTipoEstancia $hotelTipoEstancia): RedirectResponse
    {
        abort_unless(HotelCatalogoMode::usaCatalogoPersonalizado(), 404);
        abort_unless($this->canDelete($request), 403);

        if ($hotelTipoEstancia->estancias()->exists()) {
            return back()->withErrors([
                'hotel_tipo' => __('hotel.tipos.errors.en_uso'),
            ]);
        }

        $hotelTipoEstancia->delete();

        return back()->with('success', __('hotel.tipos.flash.deleted'));
    }

    private function canDelete(Request $request): bool
    {
        $user = $request->user();

        return ($user?->can('hotel.delete') ?? false) || ($user?->can('tarifas.delete') ?? false);
    }
}
