<?php

namespace App\Http\Controllers;

use App\Grooming\GroomingCatalogoMode;
use App\Http\Requests\GroomingServicioRequest;
use App\Models\GroomingServicio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GroomingServicioController extends Controller
{
    public function store(GroomingServicioRequest $request): RedirectResponse
    {
        abort_unless(GroomingCatalogoMode::usaCatalogoPersonalizado(), 404);

        $data = $request->validated();
        $maxOrden = (int) GroomingServicio::query()->max('orden');

        GroomingServicio::query()->create([
            'nombre' => $data['nombre'],
            'categoria' => $data['categoria'] ?? null,
            'precio_lista' => $data['precio_lista'],
            'moneda' => $data['moneda'] ?? 'PEN',
            'duracion_minutos' => (int) $data['duracion_minutos'],
            'activo' => $data['activo'] ?? true,
            'orden' => isset($data['orden']) ? (int) $data['orden'] : ($maxOrden + 1),
        ]);

        return back()->with('success', __('grooming.servicios.flash.created'));
    }

    public function update(GroomingServicioRequest $request, GroomingServicio $groomingServicio): RedirectResponse
    {
        abort_unless(GroomingCatalogoMode::usaCatalogoPersonalizado(), 404);

        $data = $request->validated();

        $groomingServicio->update([
            'nombre' => $data['nombre'],
            'categoria' => $data['categoria'] ?? null,
            'precio_lista' => $data['precio_lista'],
            'moneda' => $data['moneda'] ?? $groomingServicio->moneda,
            'duracion_minutos' => (int) $data['duracion_minutos'],
            'activo' => $data['activo'] ?? $groomingServicio->activo,
            'orden' => isset($data['orden']) ? (int) $data['orden'] : $groomingServicio->orden,
        ]);

        return back()->with('success', __('grooming.servicios.flash.updated'));
    }

    public function destroy(Request $request, GroomingServicio $groomingServicio): RedirectResponse
    {
        abort_unless(GroomingCatalogoMode::usaCatalogoPersonalizado(), 404);
        abort_unless(
            ($request->user()?->can('grooming.delete') ?? false)
            || ($request->user()?->can('tarifas.delete') ?? false),
            403,
        );

        if ($groomingServicio->turnos()->exists()) {
            return back()->withErrors([
                'grooming_servicio' => __('grooming.servicios.errors.en_uso'),
            ]);
        }

        $groomingServicio->delete();

        return back()->with('success', __('grooming.servicios.flash.deleted'));
    }
}
