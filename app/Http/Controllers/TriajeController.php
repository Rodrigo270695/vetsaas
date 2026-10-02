<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Paciente;
use App\Models\Triaje;
use App\Models\User;
use App\Support\Clinica\TriajeCatalogo;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class TriajeController extends Controller
{
    public function create(Request $request, Paciente $paciente): Response
    {
        $this->authorizeWrite($request, true);

        return Inertia::render('clinica/pacientes/triaje', $this->pageProps($request, $paciente, null));
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $user = $this->authorizeWrite($request, true);
        $data = $this->validated($request);

        $row = Triaje::query()->create([
            ...$data,
            'paciente_id' => $paciente->id,
            'veterinario_id' => $user->id,
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', 'Triaje guardado.')
            ->with('triaje_editar', route('clinica.pacientes.triajes.edit', [$paciente, $row]));
    }

    public function edit(Request $request, Paciente $paciente, Triaje $triaje): Response|JsonResponse
    {
        $this->authorizeWrite($request, false);
        abort_unless($triaje->paciente_id === $paciente->id, 404);
        $triaje->load('veterinario:id,name');

        $props = $this->pageProps($request, $paciente, $triaje);

        if ($request->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('clinica/pacientes/triaje', $props);
    }

    public function update(Request $request, Paciente $paciente, Triaje $triaje): RedirectResponse
    {
        $user = $this->authorizeWrite($request, true);
        abort_unless($triaje->paciente_id === $paciente->id, 404);

        $triaje->fill([
            ...$this->validated($request),
            'updated_by_id' => $user->id,
        ])->save();

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', 'Triaje actualizado.')
            ->with('triaje_editar', route('clinica.pacientes.triajes.edit', [$paciente, $triaje]));
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(Request $request, Paciente $paciente, ?Triaje $row): array
    {
        $user = $request->user();
        $puedeEditar = $user instanceof User && (
            $user->can('historias-clinicas.create')
            || $user->can('historias-clinicas.update')
            || $user->can('vacunaciones.create')
            || $user->can('vacunaciones.update')
        );
        $tz = (string) config('app.timezone', 'America/Lima');

        $registro = null;
        $atendido = Carbon::now($tz)->format('Y-m-d\TH:i');
        if ($row !== null) {
            $atendido = $row->atendido_at->timezone($tz)->format('Y-m-d\TH:i');
            $registro = [
                'id' => $row->id,
                'peso_kg' => self::numero($row->peso_kg),
                'temperatura_c' => self::numero($row->temperatura_c),
                'fc_lpm' => $row->fc_lpm !== null ? (string) $row->fc_lpm : '',
                'fr_rpm' => $row->fr_rpm !== null ? (string) $row->fr_rpm : '',
                'tlc' => self::numero($row->tlc),
                'pa' => self::numero($row->pa),
                'hidratacion' => self::numero($row->hidratacion),
                'hallazgos' => is_array($row->hallazgos) ? $row->hallazgos : [],
                'recomendaciones' => is_array($row->recomendaciones) ? array_values($row->recomendaciones) : [],
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
            'atendido_at' => $atendido,
            'puede_editar' => $puedeEditar,
            'guardar_url' => $row === null
                ? route('clinica.pacientes.triajes.store', $paciente)
                : route('clinica.pacientes.triajes.update', [$paciente, $row]),
            'method' => $row === null ? 'post' : 'put',
            'volver_url' => route('clinica.pacientes.show', $paciente),
        ];
    }

    private function authorizeWrite(Request $request, bool $needsWrite): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(Schema::hasTable('triajes'), 503, 'Falta la migración de triajes.');

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
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'temperatura_c' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'fc_lpm' => ['nullable', 'integer', 'min:0', 'max:400'],
            'fr_rpm' => ['nullable', 'integer', 'min:0', 'max:200'],
            'tlc' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'pa' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'hidratacion' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'hallazgos' => ['nullable', 'array'],
            'recomendaciones' => ['nullable', 'array'],
            'comentarios' => ['nullable', 'string', 'max:20000'],
        ]);

        $tz = (string) config('app.timezone', 'America/Lima');

        return [
            'atendido_at' => Carbon::parse($data['atendido_at'], $tz),
            'peso_kg' => $data['peso_kg'] ?? null,
            'temperatura_c' => $data['temperatura_c'] ?? null,
            'fc_lpm' => $data['fc_lpm'] ?? null,
            'fr_rpm' => $data['fr_rpm'] ?? null,
            'tlc' => $data['tlc'] ?? null,
            'pa' => $data['pa'] ?? null,
            'hidratacion' => $data['hidratacion'] ?? null,
            'hallazgos' => TriajeCatalogo::filtrarHallazgos($data['hallazgos'] ?? []),
            'recomendaciones' => TriajeCatalogo::filtrarRecomendaciones($data['recomendaciones'] ?? []),
            'comentarios' => filled($data['comentarios'] ?? null) ? trim((string) $data['comentarios']) : null,
        ];
    }

    private static function numero(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $text = (string) $value;
        if (! str_contains($text, '.')) {
            return $text;
        }

        return rtrim(rtrim($text, '0'), '.');
    }
}
