<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Antipulga;
use App\Models\Paciente;
use App\Models\Producto;
use App\Models\User;
use App\Services\Clinica\AntipulgaDictationService;
use App\Support\Clinica\RecetaFichaLineas;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

final class AntipulgaController extends Controller
{
    public function create(Request $request, Paciente $paciente): Response
    {
        $this->authorizeWrite($request, true);

        $tz = (string) config('app.timezone', 'America/Lima');

        return Inertia::render('clinica/pacientes/antipulgas', $this->pageProps(
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

        $row = Antipulga::query()->create([
            ...$data,
            'paciente_id' => $paciente->id,
            'veterinario_id' => $user->id,
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', 'Antipulgas registrada.');
    }

    public function edit(Request $request, Paciente $paciente, Antipulga $antipulga): Response|JsonResponse
    {
        $this->authorizeWrite($request, false);
        abort_unless($antipulga->paciente_id === $paciente->id, 404);
        $antipulga->load('veterinario:id,name');

        $tz = (string) config('app.timezone', 'America/Lima');
        $props = $this->pageProps(
            $request,
            $paciente,
            $antipulga,
            $antipulga->atendido_at->timezone($tz)->format('Y-m-d\TH:i'),
        );

        if ($request->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('clinica/pacientes/antipulgas', $props);
    }

    public function update(Request $request, Paciente $paciente, Antipulga $antipulga): RedirectResponse
    {
        $user = $this->authorizeWrite($request, true);
        abort_unless($antipulga->paciente_id === $paciente->id, 404);

        $antipulga->fill([
            ...$this->validated($request),
            'updated_by_id' => $user->id,
        ]);
        $antipulga->save();

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', 'Antipulgas actualizada.');
    }

    public function productosBuscar(Request $request, Paciente $paciente): JsonResponse
    {
        $this->authorizeWrite($request, true);

        $q = trim((string) $request->query('q', ''));
        $items = Producto::query()
            ->where('activo', true)
            ->when($q !== '', function ($query) use ($q): void {
                $escaped = addcslashes(mb_strtolower($q, 'UTF-8'), '%_\\');
                $term = '%'.$escaped.'%';
                $query->where(function ($inner) use ($term): void {
                    $inner->whereRaw('LOWER(nombre) LIKE ?', [$term])
                        ->orWhereRaw('LOWER(COALESCE(sku, \'\')) LIKE ?', [$term]);
                });
            })
            ->orderBy('nombre')
            ->limit(20)
            ->get(['id', 'nombre', 'sku']);

        return response()->json(['data' => $items]);
    }

    public function dictar(Request $request, Paciente $paciente, AntipulgaDictationService $dictation): JsonResponse
    {
        $this->authorizeWrite($request, true);
        abort_unless((bool) config('consulta-dictation.enabled', true), 503);
        abort_unless($dictation->isConfigured(), 503, 'Dictado no configurado (falta OPENAI_API_KEY).');

        $maxKb = max(1024, (int) config('consulta-dictation.max_audio_kb', 12288));
        $request->validate([
            'transcript' => ['nullable', 'string', 'min:3', 'max:100000'],
            'audio' => ['nullable', 'file', 'max:'.$maxKb],
        ]);

        $hasAudio = $request->hasFile('audio');
        $transcript = trim((string) $request->input('transcript', ''));
        if (! $hasAudio && $transcript === '') {
            return response()->json(['message' => 'Dicta o escribe la antipulgas.'], 422);
        }

        if ($hasAudio) {
            $ext = strtolower((string) $request->file('audio')->getClientOriginalExtension());
            $allowed = ['webm', 'wav', 'mp3', 'mp4', 'm4a', 'ogg', 'mpeg', 'x-m4a'];
            if ($ext !== '' && ! in_array($ext, $allowed, true)) {
                return response()->json(['message' => 'Formato de audio no soportado.'], 422);
            }
        }

        try {
            $result = $hasAudio
                ? $dictation->fromAudio($request->file('audio'))
                : $dictation->fromTranscript($transcript);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                'message' => $e->getMessage() !== '' ? $e->getMessage() : 'No pude procesar el dictado.',
            ], 502);
        }

        return response()->json([
            'ok' => true,
            'transcript' => $result['transcript'],
            'fields' => $result['fields'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(Request $request, Paciente $paciente, ?Antipulga $row, string $atendidoLocal): array
    {
        $user = $request->user();
        $puedeEditar = $user instanceof User && (
            $user->can('historias-clinicas.create')
            || $user->can('historias-clinicas.update')
            || $user->can('vacunaciones.create')
            || $user->can('vacunaciones.update')
        );

        $registro = null;
        if ($row !== null) {
            $registro = [
                'id' => $row->id,
                'anamnesis' => $row->anamnesis,
                'apetito' => $row->apetito,
                'ingesta_agua' => $row->ingesta_agua,
                'vomitos_frecuencia' => $row->vomitos_frecuencia,
                'vomitos_descripcion' => $row->vomitos_descripcion,
                'heces_frecuencia' => $row->heces_frecuencia,
                'heces_descripcion' => $row->heces_descripcion,
                'orina_frecuencia' => $row->orina_frecuencia,
                'orina_color' => $row->orina_color,
                'orina_olor' => $row->orina_olor,
                'ultimo_celo' => $row->ultimo_celo?->toDateString(),
                'peso_kg' => $row->peso_kg,
                'temperatura_c' => $row->temperatura_c,
                'fc_lpm' => $row->fc_lpm,
                'fr_rpm' => $row->fr_rpm,
                'tlc' => $row->tlc,
                'pa' => $row->pa,
                'hidratacion' => $row->hidratacion,
                'aplicados' => $row->aplicados ?? [],
                'receta' => $row->receta ?? [],
                'recetas' => RecetaFichaLineas::resumen('antipulga_id', $row->id),
                'comentarios' => $row->comentarios,
                'veterinario' => $row->veterinario?->name,
            ];
        }

        return [
            'paciente' => [
                'id' => $paciente->id,
                'nombre' => $paciente->nombre,
            ],
            'registro' => $registro,
            'atendido_at' => $atendidoLocal,
            'puede_editar' => $puedeEditar,
            'guardar_url' => $row === null
                ? route('clinica.pacientes.antipulgas.store', $paciente)
                : route('clinica.pacientes.antipulgas.update', [$paciente, $row]),
            'method' => $row === null ? 'post' : 'put',
            'productos_url' => route('clinica.pacientes.antipulgas.productos', $paciente),
            'dictar_url' => route('clinica.pacientes.antipulgas.dictar', $paciente),
            'cargos_url' => $row === null
                ? null
                : route('clinica.pacientes.antipulgas.cargos.show', [$paciente, $row]),
            'volver_url' => route('clinica.pacientes.show', $paciente),
        ];
    }

    private function authorizeWrite(Request $request, bool $needsWrite): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(Schema::hasTable('antipulgas'), 503, 'Falta la migración de antipulgas.');

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
            'atendido_at' => ['required', 'date'],
            'anamnesis' => ['nullable', 'string', 'max:20000'],
            'apetito' => ['nullable', 'in:disminuido,normal,aumentado'],
            'ingesta_agua' => ['nullable', 'in:disminuido,normal,aumentado'],
            'vomitos_frecuencia' => ['nullable', 'integer', 'min:0', 'max:999'],
            'vomitos_descripcion' => ['nullable', 'string', 'max:5000'],
            'heces_frecuencia' => ['nullable', 'integer', 'min:0', 'max:999'],
            'heces_descripcion' => ['nullable', 'string', 'max:5000'],
            'orina_frecuencia' => ['nullable', 'integer', 'min:0', 'max:999'],
            'orina_color' => ['nullable', 'string', 'max:160'],
            'orina_olor' => ['nullable', 'string', 'max:160'],
            'ultimo_celo' => ['nullable', 'date'],
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'temperatura_c' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'fc_lpm' => ['nullable', 'integer', 'min:0', 'max:400'],
            'fr_rpm' => ['nullable', 'integer', 'min:0', 'max:200'],
            'tlc' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'pa' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'hidratacion' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'comentarios' => ['nullable', 'string', 'max:20000'],
            'aplicados' => ['nullable', 'array', 'max:20'],
            'aplicados.*.producto_id' => ['nullable', 'uuid'],
            'aplicados.*.nombre' => ['nullable', 'string', 'max:160'],
            'aplicados.*.especificaciones' => ['nullable', 'string', 'max:500'],
            'aplicados.*.proxima_at' => ['nullable', 'date'],
            'aplicados.*.proxima_nombre' => ['nullable', 'string', 'max:160'],
            'aplicados.*.rol' => ['nullable', 'in:principal,complementario'],
            'receta' => ['nullable', 'array', 'max:30'],
            'receta.*.nombre' => ['nullable', 'string', 'max:160'],
            'receta.*.especificaciones' => ['nullable', 'string', 'max:500'],
            'receta.*.cantidad' => ['nullable', 'string', 'max:40'],
            'receta.*.producto_id' => ['nullable', 'uuid'],
        ]);

        $tz = (string) config('app.timezone', 'America/Lima');
        $blank = static fn (mixed $value): ?string => ($value === null || trim((string) $value) === '')
            ? null
            : trim((string) $value);

        $aplicados = [];
        foreach ($data['aplicados'] ?? [] as $linea) {
            $nombre = $blank($linea['nombre'] ?? null);
            if ($nombre === null) {
                continue;
            }
            $aplicados[] = [
                'producto_id' => $blank($linea['producto_id'] ?? null),
                'nombre' => $nombre,
                'especificaciones' => $blank($linea['especificaciones'] ?? null),
                'proxima_at' => $blank($linea['proxima_at'] ?? null),
                'proxima_nombre' => $blank($linea['proxima_nombre'] ?? null),
                'rol' => ($linea['rol'] ?? 'principal') === 'complementario' ? 'complementario' : 'principal',
            ];
        }

        $receta = [];
        foreach ($data['receta'] ?? [] as $linea) {
            $nombre = $blank($linea['nombre'] ?? null);
            if ($nombre === null) {
                continue;
            }
            $receta[] = [
                'producto_id' => $blank($linea['producto_id'] ?? null),
                'nombre' => $nombre,
                'especificaciones' => $blank($linea['especificaciones'] ?? null),
                'cantidad' => $blank($linea['cantidad'] ?? null),
            ];
        }

        return [
            'atendido_at' => Carbon::parse((string) $data['atendido_at'], $tz),
            'anamnesis' => $blank($data['anamnesis'] ?? null),
            'apetito' => $data['apetito'] ?? null,
            'ingesta_agua' => $data['ingesta_agua'] ?? null,
            'vomitos_frecuencia' => $blank($data['vomitos_frecuencia'] ?? null),
            'vomitos_descripcion' => $blank($data['vomitos_descripcion'] ?? null),
            'heces_frecuencia' => $blank($data['heces_frecuencia'] ?? null),
            'heces_descripcion' => $blank($data['heces_descripcion'] ?? null),
            'orina_frecuencia' => $blank($data['orina_frecuencia'] ?? null),
            'orina_color' => $blank($data['orina_color'] ?? null),
            'orina_olor' => $blank($data['orina_olor'] ?? null),
            'ultimo_celo' => $blank($data['ultimo_celo'] ?? null),
            'peso_kg' => $data['peso_kg'] ?? null,
            'temperatura_c' => $data['temperatura_c'] ?? null,
            'fc_lpm' => $data['fc_lpm'] ?? null,
            'fr_rpm' => $data['fr_rpm'] ?? null,
            'tlc' => $data['tlc'] ?? null,
            'pa' => $data['pa'] ?? null,
            'hidratacion' => $data['hidratacion'] ?? null,
            'comentarios' => $blank($data['comentarios'] ?? null),
            'aplicados' => $aplicados,
            'receta' => $receta,
        ];
    }
}
