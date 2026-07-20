<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\PetPass\AlmaPetHandoffClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Throwable;

final class PacientePetPassController extends Controller
{
    public function start(Request $request, Paciente $paciente, AlmaPetHandoffClient $client): JsonResponse|RedirectResponse|SymfonyResponse
    {
        abort_unless($request->user()?->can('petpass.register') ?? false, 403);

        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $issued = $client->createHandoff($paciente);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first()
                ?? 'No se pudo iniciar el registro en AlmaPet ID.';

            if ($wantsJson) {
                return response()->json(['message' => $message], 422);
            }

            return redirect()
                ->route('clinica.pacientes.show', $paciente)
                ->with('error', $message);
        } catch (Throwable $e) {
            report($e);

            $message = 'No se pudo conectar con AlmaPet ID. Revisa la configuración o inténtalo de nuevo.';

            if ($wantsJson) {
                return response()->json(['message' => $message], 502);
            }

            return redirect()
                ->route('clinica.pacientes.show', $paciente)
                ->with('error', $message);
        }

        if ($wantsJson) {
            return response()->json([
                'url' => $issued['url'],
                'token' => $issued['token'],
                'expires_at' => $issued['expires_at'],
            ]);
        }

        return Inertia::location($issued['url']);
    }
}
