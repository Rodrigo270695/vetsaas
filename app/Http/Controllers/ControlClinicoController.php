<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ControlClinico;
use App\Models\Paciente;
use App\Support\Inventario\ProductoCatalogoSearch;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

final class ControlClinicoController extends Controller
{
    public function create(Request $request, Paciente $paciente): Response
    {
        $this->authorizeWrite($request, true);

        $tz = (string) config('app.timezone', 'America/Lima');

        return Inertia::render('clinica/pacientes/control', $this->pageProps(
            $request,
            $paciente,
            null,
            Carbon::now($tz)->format('Y-m-d\TH:i'),
        ));
    }

    public function store(Request $request, Paciente $paciente): RedirectResponse
    {
        $user = $this->authorizeWrite($request, true);

        ControlClinico::query()->create([
            ...$this->validated($request),
            'paciente_id' => $paciente->id,
            'veterinario_id' => $user->id,
            'created_by_id' => $user->id,
            'updated_by_id' => $user->id,
        ]);

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', 'Control registrado.');
    }

    public function edit(Request $request, Paciente $paciente, ControlClinico $control): Response|JsonResponse
    {
        $this->authorizeWrite($request, false);
        abort_unless($control->paciente_id === $paciente->id, 404);
        $control->load('veterinario:id,name');

        $tz = (string) config('app.timezone', 'America/Lima');
        $props = $this->pageProps(
            $request,
            $paciente,
            $control,
            $control->atendido_at->timezone($tz)->format('Y-m-d\TH:i'),
        );

        if ($request->wantsJson()) {
            return response()->json($props);
        }

        return Inertia::render('clinica/pacientes/control', $props);
    }

    public function update(Request $request, Paciente $paciente, ControlClinico $control): RedirectResponse
    {
        $user = $this->authorizeWrite($request, true);
        abort_unless($control->paciente_id === $paciente->id, 404);

        $control->fill([
            ...$this->validated($request),
            'updated_by_id' => $user->id,
        ]);
        $control->save();

        return redirect()
            ->route('clinica.pacientes.show', $paciente)
            ->with('success', 'Control actualizado.');
    }

    public function productosBuscar(Request $request, Paciente $paciente): JsonResponse
    {
        $this->authorizeWrite($request, true);

        $q = trim((string) $request->query('q', ''));

        return response()->json([
            'data' => ProductoCatalogoSearch::filas($q, soloActivos: true),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageProps(Request $request, Paciente $paciente, ?ControlClinico $row, string $atendidoLocal): array
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
                'motivo' => $row->motivo,
                'anamnesis' => $row->anamnesis,
                'anamnesis_detalle' => $row->anamnesis_detalle,
                'peso_kg' => $row->peso_kg,
                'temperatura_c' => $row->temperatura_c,
                'fc_lpm' => $row->fc_lpm,
                'fr_rpm' => $row->fr_rpm,
                'tlc' => $row->tlc,
                'pa' => $row->pa,
                'hidratacion' => $row->hidratacion,
                'examen_clinico' => $row->examen_clinico,
                'examen_detalle' => $row->examen_detalle,
                'diagnosticos' => $row->diagnosticos ?? [],
                'examenes' => $row->examenes ?? [],
                'tratamiento' => $row->tratamiento ?? [],
                'receta' => $row->receta ?? [],
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
                ? route('clinica.pacientes.controles.store', $paciente)
                : route('clinica.pacientes.controles.update', [$paciente, $row]),
            'method' => $row === null ? 'post' : 'put',
            'productos_url' => route('clinica.pacientes.controles.productos', $paciente),
            'volver_url' => route('clinica.pacientes.show', $paciente),
        ];
    }

    private function authorizeWrite(Request $request, bool $needsWrite): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        abort_unless(Schema::hasTable('controles'), 503, 'Falta la migración de control.');

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
            'motivo' => ['nullable', 'string', 'max:200'],
            'anamnesis' => ['nullable', 'string', 'max:20000'],
            'anamnesis_detalle' => ['nullable', 'string', 'max:20000'],
            'peso_kg' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'temperatura_c' => ['nullable', 'numeric', 'min:0', 'max:50'],
            'fc_lpm' => ['nullable', 'integer', 'min:0', 'max:400'],
            'fr_rpm' => ['nullable', 'integer', 'min:0', 'max:200'],
            'tlc' => ['nullable', 'numeric', 'min:0', 'max:99'],
            'pa' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'hidratacion' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'examen_clinico' => ['nullable', 'string', 'max:20000'],
            'examen_detalle' => ['nullable', 'string', 'max:20000'],
            'comentarios' => ['nullable', 'string', 'max:20000'],
            'diagnosticos' => ['nullable', 'array', 'max:30'],
            'diagnosticos.*' => ['nullable', 'string', 'max:200'],
            'examenes' => ['nullable', 'array', 'max:30'],
            'examenes.*.producto_id' => ['nullable', 'uuid'],
            'examenes.*.nombre' => ['nullable', 'string', 'max:200'],
            'examenes.*.especificaciones' => ['nullable', 'string', 'max:500'],
            'examenes.*.rol' => ['nullable', 'in:principal,complementario'],
            'tratamiento' => ['nullable', 'array', 'max:30'],
            'tratamiento.*.producto_id' => ['nullable', 'uuid'],
            'tratamiento.*.nombre' => ['nullable', 'string', 'max:200'],
            'tratamiento.*.especificaciones' => ['nullable', 'string', 'max:500'],
            'tratamiento.*.cantidad' => ['nullable', 'string', 'max:40'],
            'tratamiento.*.rol' => ['nullable', 'in:principal,complementario'],
            'receta' => ['nullable', 'array', 'max:30'],
            'receta.*.producto_id' => ['nullable', 'uuid'],
            'receta.*.nombre' => ['nullable', 'string', 'max:200'],
            'receta.*.especificaciones' => ['nullable', 'string', 'max:500'],
            'receta.*.cantidad' => ['nullable', 'string', 'max:40'],
            'receta.*.rol' => ['nullable', 'in:principal,complementario'],
        ]);

        $tz = (string) config('app.timezone', 'America/Lima');
        $blank = static function (mixed $value): ?string {
            if ($value === null) {
                return null;
            }
            $text = trim((string) $value);

            return $text === '' ? null : $text;
        };

        $diagnosticos = [];
        foreach ($data['diagnosticos'] ?? [] as $item) {
            $nombre = $blank($item);
            if ($nombre !== null) {
                $diagnosticos[] = $nombre;
            }
        }

        $lineas = static function (array $rows, bool $conCantidad) use ($blank): array {
            $out = [];
            foreach ($rows as $linea) {
                $nombre = $blank($linea['nombre'] ?? null);
                if ($nombre === null) {
                    continue;
                }
                $item = [
                    'producto_id' => $blank($linea['producto_id'] ?? null),
                    'nombre' => $nombre,
                    'especificaciones' => $blank($linea['especificaciones'] ?? null),
                    'rol' => ($linea['rol'] ?? 'principal') === 'complementario' ? 'complementario' : 'principal',
                ];
                if ($conCantidad) {
                    $item['cantidad'] = $blank($linea['cantidad'] ?? null);
                }
                $out[] = $item;
            }

            return $out;
        };

        return [
            'atendido_at' => Carbon::parse((string) $data['atendido_at'], $tz),
            'motivo' => $blank($data['motivo'] ?? null) ?? 'Control',
            'anamnesis' => $blank($data['anamnesis'] ?? null),
            'anamnesis_detalle' => $blank($data['anamnesis_detalle'] ?? null),
            'peso_kg' => $data['peso_kg'] ?? null,
            'temperatura_c' => $data['temperatura_c'] ?? null,
            'fc_lpm' => $data['fc_lpm'] ?? null,
            'fr_rpm' => $data['fr_rpm'] ?? null,
            'tlc' => $blank($data['tlc'] ?? null),
            'pa' => $blank($data['pa'] ?? null),
            'hidratacion' => $blank($data['hidratacion'] ?? null),
            'examen_clinico' => $blank($data['examen_clinico'] ?? null),
            'examen_detalle' => $blank($data['examen_detalle'] ?? null),
            'diagnosticos' => $diagnosticos,
            'examenes' => $lineas($data['examenes'] ?? [], false),
            'tratamiento' => $lineas($data['tratamiento'] ?? [], true),
            'receta' => $lineas($data['receta'] ?? [], true),
            'comentarios' => $blank($data['comentarios'] ?? null),
        ];
    }
}
