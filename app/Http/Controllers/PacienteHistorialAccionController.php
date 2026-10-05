<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Antipulga;
use App\Models\Defuncion;
use App\Models\Desparasitacion;
use App\Models\DocumentoAutorizacionPlantilla;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\Triaje;
use App\Services\Clinica\DocumentoAutorizacionService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class PacienteHistorialAccionController extends Controller
{
    public function destroy(Request $request, Paciente $paciente, string $kind, string $id): RedirectResponse
    {
        $user = $request->user();
        abort_unless(
            $user !== null && ($user->can('historias-clinicas.delete') || $user->can('vacunaciones.delete')),
            403,
        );

        $row = $this->registro($paciente, $kind, $id);
        $row->delete();

        return redirect()->back()->with('success', 'Registro eliminado del historial.');
    }

    public function autorizacion(
        Request $request,
        Paciente $paciente,
        DocumentoAutorizacionService $service,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless(
            $user !== null && ($user->can('historias-clinicas.update') || $user->can('vacunaciones.update')),
            403,
        );

        $request->merge([
            'enviar_whatsapp' => $request->boolean('enviar_whatsapp'),
            'enviar_email' => $request->boolean('enviar_email'),
        ]);

        $data = $request->validate([
            'plantilla_id' => ['required', 'uuid', 'exists:documento_autorizacion_plantillas,id'],
            'telefono' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:150'],
            'enviar_whatsapp' => ['required', 'boolean'],
            'enviar_email' => ['required', 'boolean'],
        ]);

        if (! $data['enviar_whatsapp'] && ! $data['enviar_email']) {
            return back()->with('warning', 'Elige al menos WhatsApp o correo.');
        }

        $plantilla = DocumentoAutorizacionPlantilla::query()
            ->where('activo', true)
            ->findOrFail($data['plantilla_id']);

        $tenantId = tenant_id();
        $tenant = $tenantId !== null ? Tenant::query()->find($tenantId) : null;
        if ($tenant === null || ! is_string($tenant->slug) || $tenant->slug === '') {
            return back()->with('warning', 'No se pudo identificar la clínica.');
        }

        $result = $service->emitirParaPaciente(
            $paciente,
            $plantilla,
            $tenant,
            $this->texto($request->query('motivo')),
            $this->texto($request->query('fecha')),
            $this->texto($request->query('veterinario')),
            $data['telefono'] ?? null,
            $data['email'] ?? null,
            (bool) $data['enviar_whatsapp'],
            (bool) $data['enviar_email'],
            is_scalar($user->id) ? (string) $user->id : null,
        );

        if ($result['warnings'] !== [] && ! $result['whatsapp_ok'] && ! $result['email_ok']) {
            return back()->with('warning', $result['warnings'][0] ?? 'No se pudo enviar el documento.');
        }

        $msg = 'Documento enviado al titular.';
        if ($result['warnings'] !== []) {
            return back()->with('success', $msg)->with('warning', implode(' ', $result['warnings']));
        }

        return back()->with('success', $msg);
    }

    private function registro(Paciente $paciente, string $kind, string $id): Model
    {
        $query = match ($kind) {
            'desparasitacion' => Desparasitacion::query(),
            'antipulga' => Antipulga::query(),
            'triaje' => Triaje::query(),
            'defuncion' => Defuncion::query(),
            default => abort(404),
        };

        return $query
            ->where('paciente_id', $paciente->id)
            ->whereKey($id)
            ->firstOrFail();
    }

    private function texto(mixed $value): string
    {
        $text = trim((string) $value);
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return mb_substr($text, 0, 160);
    }
}
