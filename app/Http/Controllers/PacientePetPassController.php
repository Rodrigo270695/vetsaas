<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\PetPass\AlmaPetHandoffClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class PacientePetPassController extends Controller
{
    public function start(Request $request, Paciente $paciente, AlmaPetHandoffClient $client): RedirectResponse|SymfonyResponse
    {
        abort_unless($request->user()?->can('petpass.register') ?? false, 403);

        try {
            $issued = $client->createHandoff($paciente);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first()
                ?? 'No se pudo iniciar el registro en AlmaPet ID.';

            return redirect()
                ->route('clinica.pacientes.show', $paciente)
                ->with('error', $message);
        } catch (Throwable $e) {
            report($e);

            return redirect()
                ->route('clinica.pacientes.show', $paciente)
                ->with('error', 'No se pudo conectar con AlmaPet ID. Revisa la configuración o inténtalo de nuevo.');
        }

        // Inertia::location fuerza salida del SPA hacia URL externa (evita “solo recarga”).
        return Inertia::location($issued['url']);
    }
}
