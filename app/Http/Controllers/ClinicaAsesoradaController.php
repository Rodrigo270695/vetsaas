<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ClinicaAsesoradaRequest;
use App\Models\ClinicaAsesorada;
use App\Models\ClinicSetting;
use App\Models\Departamento;
use App\Models\Distrito;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ClinicaAsesoradaController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50];

    public function index(Request $request): Response
    {
        $this->abortUnlessModoAsesora();

        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true) ? $perPageRequested : 10;
        $estado = (string) $request->string('estado', 'todas');
        if (! in_array($estado, ['todas', 'activa', 'inactiva'], true)) {
            $estado = 'todas';
        }

        $query = ClinicaAsesorada::query()
            ->withCount('pacientes')
            ->orderBy('nombre');

        if ($search !== '') {
            $query->where(function ($q) use ($search): void {
                $q->where('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('ruc', 'ILIKE', "%{$search}%")
                    ->orWhere('direccion', 'ILIKE', "%{$search}%")
                    ->orWhere('distrito', 'ILIKE', "%{$search}%")
                    ->orWhere('provincia', 'ILIKE', "%{$search}%")
                    ->orWhere('departamento', 'ILIKE', "%{$search}%");
            });
        }

        if ($estado === 'activa') {
            $query->where('activo', true);
        } elseif ($estado === 'inactiva') {
            $query->where('activo', false);
        }

        $items = $query->paginate($perPage)->withQueryString();

        return Inertia::render('clinica/clinicas-asesoradas/index', [
            'items' => $items->through(fn (ClinicaAsesorada $row): array => $this->present($row)),
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'estado' => $estado,
            ],
            'stats' => [
                'total' => ClinicaAsesorada::count(),
                'activas' => ClinicaAsesorada::where('activo', true)->count(),
                'inactivas' => ClinicaAsesorada::where('activo', false)->count(),
                'coincidencias' => $items->total(),
            ],
            'departamentos' => Departamento::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    public function store(ClinicaAsesoradaRequest $request): RedirectResponse
    {
        $this->abortUnlessModoAsesora();

        $data = $this->hydrateLocationFromDistrito($request->validated());
        $userId = Auth::id();

        ClinicaAsesorada::query()->create([
            ...$data,
            'created_by_id' => $userId,
            'updated_by_id' => $userId,
        ]);

        return back()->with('success', 'Clínica registrada correctamente.');
    }

    public function update(ClinicaAsesoradaRequest $request, ClinicaAsesorada $clinicaAsesorada): RedirectResponse
    {
        $this->abortUnlessModoAsesora();

        $clinicaAsesorada->update([
            ...$this->hydrateLocationFromDistrito($request->validated()),
            'updated_by_id' => Auth::id(),
        ]);

        return back()->with('success', 'Clínica actualizada correctamente.');
    }

    public function destroy(ClinicaAsesorada $clinicaAsesorada): RedirectResponse
    {
        $this->abortUnlessModoAsesora();

        $clinicaAsesorada->delete();

        return back()->with('success', 'Clínica eliminada correctamente.');
    }

    private function abortUnlessModoAsesora(): void
    {
        abort_unless(
            (bool) ClinicSetting::query()->value('modo_asesora_activo'),
            404,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ClinicaAsesorada $row): array
    {
        return [
            'id' => $row->id,
            'nombre' => $row->nombre,
            'ruc' => $row->ruc,
            'direccion' => $row->direccion,
            'distrito_id' => $row->distrito_id,
            'distrito' => $row->distrito,
            'provincia' => $row->provincia,
            'departamento' => $row->departamento,
            'activo' => (bool) $row->activo,
            'mascotas_count' => (int) ($row->pacientes_count ?? 0),
            'updated_at' => $row->updated_at?->toIso8601String(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function hydrateLocationFromDistrito(array $data): array
    {
        $distritoId = $data['distrito_id'] ?? null;

        if ($distritoId === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $distrito = Distrito::query()
            ->with('provincia.departamento')
            ->find($distritoId);

        if ($distrito === null) {
            $data['distrito'] = null;
            $data['provincia'] = null;
            $data['departamento'] = null;

            return $data;
        }

        $data['distrito'] = $distrito->name;
        $data['provincia'] = $distrito->provincia?->name;
        $data['departamento'] = $distrito->provincia?->departamento?->name;

        return $data;
    }
}
