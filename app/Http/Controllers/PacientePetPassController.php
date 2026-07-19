<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\PetPass\AlmaPetHandoffClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

final class PacientePetPassController extends Controller
{
    public function start(Request $request, Paciente $paciente, AlmaPetHandoffClient $client): RedirectResponse
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
        }

        return redirect()->away($issued['url']);
    }
}
