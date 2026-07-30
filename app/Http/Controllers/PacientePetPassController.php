<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Services\PetPass\AlmaPetHandoffClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

final class PacientePetPassController extends Controller
{
    public function start(Request $request, Paciente $paciente, AlmaPetHandoffClient $client): JsonResponse|RedirectResponse
    {
        abort_unless($request->user()?->can('petpass.register') ?? false, 403);

        $wantsJson = $request->expectsJson() || $request->ajax();

        try {
            $result = $client->registerWithoutCharge($paciente);
        } catch (ValidationException $e) {
            $message = collect($e->errors())->flatten()->first()
                ?? 'No se pudo registrar en AlmaPet ID.';

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

        $success = 'Mascota registrada en AlmaPet ID (pendiente de activación del dueño).';
        if ($result['whatsapp_sent']) {
            $success .= ' Se envió el link de activación por WhatsApp de la clínica.';
        } elseif ($result['whatsapp_error']) {
            $success .= ' No se pudo enviar WhatsApp: '.$result['whatsapp_error'];
        }

        if ($wantsJson) {
            return response()->json([
                'ok' => true,
                'activate_url' => $result['activate_url'],
                'public_code' => $result['public_code'],
                'whatsapp_sent' => $result['whatsapp_sent'],
                'whatsapp_error' => $result['whatsapp_error'],
                'message' => $success,
            ]);
        }

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', $success);
    }
}
