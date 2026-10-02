<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Defuncion;
use App\Models\DocumentoAutorizacionEnvio;
use App\Models\DocumentoAutorizacionPlantilla;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Clinica\DocumentoAutorizacionService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class DefuncionController extends Controller
{
    public function create(Request $request, Paciente $paciente): Response
    {
        $this->authorizeWrite($request, true);

        $tz = (string) config('app.timezone', 'America/Lima');

        return Inertia::render('clinica/pacientes/defuncion', $this->pageProps(
            $request,
            $paciente,
            null,
            Carbon::now($tz)->format('Y-m-d\TH:i'),
        ));
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $user = $this->authorizeWrite($request, true);
        $data = $this->validated($request);

        $row = Defuncion::query()->create([
            ...$data,
            'paciente_id' => $paciente->id,
            'veterinario_id' => $user->id,
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', 'Certificado de defunción guardado. Envía la autorización para cambiar el estado de la mascota.')
            ->with('defuncion_editar', route('clinica.pacientes.defunciones.edit', [$paciente, $row]));
    }

    public function edit(Request $request, Paciente $paciente, Defuncion $defuncion): Response|JsonResponse
    {
        $this->authorizeWrite($request, false);
        abort_unless($defuncion->paciente_id === $paciente->id, 404);
        $defuncion->load('veterinario:id,name');
        $this->sincronizarFallecido($paciente, $defuncion);

        $tz = (string) config('app.timezone', 'America/Lima');
        $props = $this->pageProps(
            $request,
            $paciente->fresh() ?? $paciente,
            $defuncion,
            $defuncion->ocurrido_at->timezone($tz)->format('Y-m-d\TH:i'),
        );

        if ($request->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('clinica/pacientes/defuncion', $props);
    }

    public function update(Request $request, Paciente $paciente, Defuncion $defuncion): RedirectResponse
    {
        $user = $this->authorizeWrite($request, true);
        abort_unless($defuncion->paciente_id === $paciente->id, 404);
        $data = $this->validated($request);

        $defuncion->fill([
            ...$data,
            'updated_by_id' => $user->id,
        ])->save();

        $this->sincronizarFallecido($paciente, $defuncion);

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', 'Certificado de defunción actualizado.')
            ->with('defuncion_editar', route('clinica.pacientes.defunciones.edit', [$paciente, $defuncion]));
    }

    public function autorizacion(
        Request $request,
        Paciente $paciente,
        Defuncion $defuncion,
        DocumentoAutorizacionService $service,
    ): RedirectResponse {
        $user = $this->authorizeWrite($request, true);
        abort_unless($defuncion->paciente_id === $paciente->id, 404);

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

        $result = $service->emitirParaDefuncion(
            $defuncion,
            $paciente,
            $plantilla,
            $tenant,
            $data['telefono'] ?? null,
            $data['email'] ?? null,
            (bool) $data['enviar_whatsapp'],
            (bool) $data['enviar_email'],
            $user->id,
        );

        $editUrl = route('clinica.pacientes.defunciones.edit', [$paciente, $defuncion]);

        if ($result['warnings'] !== [] && ! $result['whatsapp_ok'] && ! $result['email_ok']) {
            return back()
                ->with('warning', $result['warnings'][0] ?? 'No se pudo enviar el documento.')
                ->with('defuncion_editar', $editUrl);
        }

        $msg = 'Autorización enviada al titular. La mascota pasará a fallecida cuando firme.';
        if ($result['warnings'] !== []) {
            return back()
                ->with('success', $msg)
                ->with('warning', implode(' ', $result['warnings']))
                ->with('defuncion_editar', $editUrl);
        }

        return back()
            ->with('success', $msg)
            ->with('defuncion_editar', $editUrl);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(Request $request, Paciente $paciente, ?Defuncion $row, string $ocurridoLocal): array
    {
        $user = $request->user();
        $puedeEditar = $user instanceof User && (
            $user->can('historias-clinicas.create')
            || $user->can('historias-clinicas.update')
            || $user->can('vacunaciones.create')
            || $user->can('vacunaciones.update')
        );

        $paciente->loadMissing('propietario:id,telefono,email');

        $plantillas = Schema::hasTable('documento_autorizacion_plantillas')
            ? DocumentoAutorizacionPlantilla::query()
                ->where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'descripcion'])
                ->map(fn (DocumentoAutorizacionPlantilla $p): array => [
                    'id' => $p->id,
                    'nombre' => $p->nombre,
                    'descripcion' => $p->descripcion,
                ])
                ->values()
                ->all()
            : [];

        $plantillaDefuncionId = null;
        foreach ($plantillas as $plantilla) {
            if ($plantilla['nombre'] === DocumentoAutorizacionPlantilla::NOMBRE_DEFUNCION) {
                $plantillaDefuncionId = $plantilla['id'];
                break;
            }
        }

        $registro = null;
        if ($row !== null) {
            $envio = $this->ultimoEnvio($row);
            $registro = [
                'id' => $row->id,
                'motivo' => $row->motivo,
                'sitio' => $row->sitio,
                'testigo' => $row->testigo,
                'comentarios' => $row->comentarios,
                'veterinario' => $row->veterinario?->name,
                'autorizacion' => $envio === null ? null : [
                    'estado' => $envio->estado,
                    'firmado_at' => $envio->firmado_at?->toIso8601String(),
                    'pdf_url' => $envio->pdf_url,
                    'titulo' => $envio->titulo,
                ],
            ];
        }

        return [
            'paciente' => [
                'id' => $paciente->id,
                'nombre' => $paciente->nombre,
                'fallecido' => $paciente->fallecido_at !== null,
            ],
            'registro' => $registro,
            'ocurrido_at' => $ocurridoLocal,
            'puede_editar' => $puedeEditar,
            'guardar_url' => $row === null
                ? route('clinica.pacientes.defunciones.store', $paciente)
                : route('clinica.pacientes.defunciones.update', [$paciente, $row]),
            'method' => $row === null ? 'post' : 'put',
            'autorizar_url' => $row === null
                ? null
                : route('clinica.pacientes.defunciones.autorizacion', [$paciente, $row]),
            'plantillas' => $plantillas,
            'plantilla_defuncion_id' => $plantillaDefuncionId,
            'telefono' => (string) ($paciente->propietario?->telefono ?? ''),
            'email' => (string) ($paciente->propietario?->email ?? ''),
            'volver_url' => route('clinica.pacientes.show', $paciente),
        ];
    }

    private function ultimoEnvio(Defuncion $row): ?DocumentoAutorizacionEnvio
    {
        if (! Schema::hasColumn('documento_autorizacion_envios', 'defuncion_id')) {
            return null;
        }

        return DocumentoAutorizacionEnvio::query()
            ->where('defuncion_id', $row->id)
            ->orderByDesc('created_at')
            ->first();
    }

    private function sincronizarFallecido(Paciente $paciente, Defuncion $defuncion): void
    {
        $envio = $this->ultimoEnvio($defuncion);
        if ($envio === null || $envio->estado !== DocumentoAutorizacionEnvio::ESTADO_FIRMADO) {
            return;
        }

        app(DocumentoAutorizacionService::class)->marcarPacienteFallecido($paciente, $defuncion);
    }

    private function authorizeWrite(Request $request, bool $needsWrite): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(Schema::hasTable('defunciones'), 503, 'Falta la migración de defunciones.');

        $ok = $needsWrite
            ? ($user->can('historias-clinicas.create')
                || $user->can('historias-clinicas.update')
                || $user->can('vacunaciones.create')
                || $user->can('vacunaciones.update'))
            : ($user->can('historias-clinicas.view')
                || $user->can('historias-clinicas.update')
                || $user->can('vacunaciones.view')
                || $user->can('vacunaciones.update')
                || $user->can('historias-clinicas.create')
                || $user->can('vacunaciones.create'));

        abort_unless($ok, 403);

        return $user;
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'ocurrido_at' => ['required', 'date'],
            'motivo' => ['required', 'string', 'max:200'],
            'sitio' => ['nullable', 'string', 'max:255'],
            'testigo' => ['nullable', 'string', 'max:255'],
            'comentarios' => ['nullable', 'string', 'max:20000'],
        ]);

        $tz = (string) config('app.timezone', 'America/Lima');
        $data['ocurrido_at'] = Carbon::parse((string) $data['ocurrido_at'], $tz);
        $data['motivo'] = trim((string) $data['motivo']);
        $data['sitio'] = $this->blankToNull($data['sitio'] ?? null);
        $data['testigo'] = $this->blankToNull($data['testigo'] ?? null);
        $data['comentarios'] = $this->blankToNull($data['comentarios'] ?? null);

        return $data;
    }

    private function blankToNull(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
