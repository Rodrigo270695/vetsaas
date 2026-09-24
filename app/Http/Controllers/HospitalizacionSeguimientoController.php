<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInternamientoFluidoRequest;
use App\Http\Requests\StoreInternamientoNotaRequest;
use App\Http\Requests\StoreInternamientoTratamientoRequest;
use App\Http\Requests\UpdateInternamientoFluidoRequest;
use App\Http\Requests\UpdateInternamientoNotaRequest;
use App\Http\Requests\UpdateInternamientoTratamientoRequest;
use App\Models\Internamiento;
use App\Models\InternamientoFluido;
use App\Models\InternamientoNota;
use App\Models\InternamientoTratamiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HospitalizacionSeguimientoController extends Controller
{
    public function storeNota(StoreInternamientoNotaRequest $request, Internamiento $internamiento): RedirectResponse
    {
        InternamientoNota::query()->create([
            ...$request->validated(),
            'internamiento_id' => $internamiento->id,
            'created_by_id' => Auth::id(),
            'updated_by_id' => Auth::id(),
        ]);

        return $this->volver($internamiento, 'hospitalizacion.flash.nota_created');
    }

    public function updateNota(
        UpdateInternamientoNotaRequest $request,
        Internamiento $internamiento,
        InternamientoNota $nota,
    ): RedirectResponse {
        abort_unless($nota->internamiento_id === $internamiento->id, 404);

        $nota->fill($request->validated());
        $nota->updated_by_id = Auth::id();
        $nota->save();

        return $this->volver($internamiento, 'hospitalizacion.flash.nota_updated');
    }

    public function destroyNota(Request $request, Internamiento $internamiento, InternamientoNota $nota): RedirectResponse
    {
        abort_unless($request->user()?->can('hospitalizacion.update') ?? false, 403);
        abort_unless($nota->internamiento_id === $internamiento->id, 404);
        $nota->delete();

        return $this->volver($internamiento, 'hospitalizacion.flash.nota_deleted');
    }

    public function storeFluido(StoreInternamientoFluidoRequest $request, Internamiento $internamiento): RedirectResponse
    {
        InternamientoFluido::query()->create([
            ...$request->validated(),
            'internamiento_id' => $internamiento->id,
            'created_by_id' => Auth::id(),
            'updated_by_id' => Auth::id(),
        ]);

        return $this->volver($internamiento, 'hospitalizacion.flash.fluido_created');
    }

    public function updateFluido(
        UpdateInternamientoFluidoRequest $request,
        Internamiento $internamiento,
        InternamientoFluido $fluido,
    ): RedirectResponse {
        abort_unless($fluido->internamiento_id === $internamiento->id, 404);

        $fluido->fill($request->validated());
        $fluido->updated_by_id = Auth::id();
        $fluido->save();

        return $this->volver($internamiento, 'hospitalizacion.flash.fluido_updated');
    }

    public function destroyFluido(Request $request, Internamiento $internamiento, InternamientoFluido $fluido): RedirectResponse
    {
        abort_unless($request->user()?->can('hospitalizacion.update') ?? false, 403);
        abort_unless($fluido->internamiento_id === $internamiento->id, 404);
        $fluido->delete();

        return $this->volver($internamiento, 'hospitalizacion.flash.fluido_deleted');
    }

    public function storeTratamiento(
        StoreInternamientoTratamientoRequest $request,
        Internamiento $internamiento,
    ): RedirectResponse {
        InternamientoTratamiento::query()->create([
            ...$request->validated(),
            'internamiento_id' => $internamiento->id,
            'created_by_id' => Auth::id(),
            'updated_by_id' => Auth::id(),
        ]);

        return $this->volver($internamiento, 'hospitalizacion.flash.tratamiento_created');
    }

    public function updateTratamiento(
        UpdateInternamientoTratamientoRequest $request,
        Internamiento $internamiento,
        InternamientoTratamiento $tratamiento,
    ): RedirectResponse {
        abort_unless($tratamiento->internamiento_id === $internamiento->id, 404);

        $tratamiento->fill($request->validated());
        $tratamiento->updated_by_id = Auth::id();
        $tratamiento->save();

        return $this->volver($internamiento, 'hospitalizacion.flash.tratamiento_updated');
    }

    public function destroyTratamiento(
        Request $request,
        Internamiento $internamiento,
        InternamientoTratamiento $tratamiento,
    ): RedirectResponse {
        abort_unless($request->user()?->can('hospitalizacion.update') ?? false, 403);
        abort_unless($tratamiento->internamiento_id === $internamiento->id, 404);
        $tratamiento->delete();

        return $this->volver($internamiento, 'hospitalizacion.flash.tratamiento_deleted');
    }

    public function alta(Request $request, Internamiento $internamiento): RedirectResponse
    {
        abort_unless($request->user()?->can('hospitalizacion.update') ?? false, 403);

        $internamiento->estado = Internamiento::ESTADO_ALTA;
        $internamiento->alta_at ??= now(config('app.timezone'));
        $internamiento->updated_by_id = Auth::id();
        $internamiento->save();

        return $this->volver($internamiento, 'hospitalizacion.flash.alta');
    }

    private function volver(Internamiento $internamiento, string $flash): RedirectResponse
    {
        return redirect()
            ->route('clinica.hospitalizacion.show', $internamiento)
            ->with('success', __($flash));
    }
}
