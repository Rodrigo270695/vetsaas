<?php

namespace App\Http\Controllers;

use App\Exports\PlansXlsxExport;
use App\Http\Requests\PlanRequest;
use App\Models\Plan;
use App\Models\PlanFeature;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Administración del catálogo de planes (Plataforma → Planes).
 *
 * Notas de diseño:
 *   - El `codigo` del plan **no se cambia** después de creado: el
 *     código de la app lo consulta para resolver features (ej.
 *     `Plan::find('starter')->resolveFeature('max_sedes')`). Si se
 *     necesita renombrar, mejor crear otro plan y migrar.
 *   - No permitimos eliminar un plan que tenga suscripciones activas
 *     o en gracia (FK protegida + chequeo defensivo).
 *   - Las features se gestionan vía `updateFeatures` (endpoint dedicado)
 *     porque su UI es lo bastante diferente como para tener un modal
 *     propio (al estilo `RolePermissionsModal`).
 */
class PlanController extends Controller
{
    private const PER_PAGE_OPTIONS = [10, 15, 20, 25, 50, 100];

    private const SORTABLE_COLUMNS = [
        'codigo',
        'nombre',
        'precio_mensual',
        'orden',
        'created_at',
    ];

    private const ESTADO_OPTIONS = ['todos', 'activos', 'inactivos', 'publicos', 'privados'];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search', ''));
        $perPageRequested = (int) $request->integer('per_page', 10);
        $perPage = in_array($perPageRequested, self::PER_PAGE_OPTIONS, true)
            ? $perPageRequested
            : 10;

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'asc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $query = $this->buildBaseQuery($search, $estado);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderBy('orden');
        } else {
            $query->orderBy('orden')->orderBy('precio_mensual');
        }

        $plans = $query
            ->withCount(['features', 'subscriptions'])
            ->with([
                // Cargamos las features inline para que el modal de
                // gestión las muestre sin hacer un round-trip extra.
                // Es seguro: el paginador típico es 10-25 planes, y
                // FEATURE_CATALOG tiene ~20 entradas por plan máximo.
                'features:plan_id,feature,valor_int,valor_bool,valor_str',
            ])
            ->paginate($perPage)
            ->withQueryString();

        // Catálogo de features (key + tipo + grupo) para que el modal
        // sepa qué inputs pintar. Lo enviamos siempre porque es la
        // fuente de verdad del UI; no depende del plan seleccionado.
        $featureCatalog = collect(Plan::FEATURE_CATALOG)->map(
            fn (array $meta, string $feature) => [
                'feature' => $feature,
                'type' => $meta['type'],
                'group' => $meta['group'],
                'default' => $meta['default'],
            ],
        )->values();

        // Estadísticas globales para los chips/cards del header.
        $stats = [
            'total' => Plan::query()->count(),
            'activos' => Plan::query()->where('activo', true)->count(),
            'inactivos' => Plan::query()->where('activo', false)->count(),
            'publicos' => Plan::query()->where('es_publico', true)->count(),
            'coincidencias' => $plans->total(),
        ];

        return Inertia::render('plataforma/planes/index', [
            'plans' => $plans,
            'filters' => [
                'search' => $search,
                'per_page' => $perPage,
                'sort' => $sortValid ? $sort : null,
                'direction' => $sortValid && $directionValid ? $direction : null,
                'estado' => $estado,
            ],
            'stats' => $stats,
            'feature_catalog' => $featureCatalog,
        ]);
    }

    public function store(PlanRequest $request): RedirectResponse
    {
        Plan::create($request->validated());

        \Illuminate\Support\Facades\Cache::forget('salesbot_plans_vetsaas');

        return back()->with('success', 'Plan creado correctamente.');
    }

    public function update(PlanRequest $request, Plan $plan): RedirectResponse
    {
        $data = $request->validated();

        // El código del plan NO se modifica después de creado.
        if ($plan->codigo !== $data['codigo']) {
            throw ValidationException::withMessages([
                'codigo' => 'No puedes cambiar el código de un plan ya creado. Crea un plan nuevo y archiva este.',
            ]);
        }

        $plan->update($data);

        // Invalidar el caché del bot de ventas para que use los precios nuevos.
        \Illuminate\Support\Facades\Cache::forget('salesbot_plans_vetsaas');
        \Illuminate\Support\Facades\Cache::forget('salesbot_knowledge_vetsaas_no_plans');

        return back()->with('success', 'Plan actualizado correctamente.');
    }

    /**
     * Reemplaza el set de features asociadas al plan.
     *
     * El payload es un array `{ feature, valor_int?, valor_bool?, valor_str? }`.
     * Solo aceptamos features que vivan en `Plan::FEATURE_CATALOG`.
     *
     * Si la UI envía una feature sin valor (todos los `valor_*` en null),
     * la interpretamos como "desactivada" y la eliminamos para no llenar
     * la tabla con filas inútiles.
     */
    public function updateFeatures(Request $request, Plan $plan): RedirectResponse
    {
        $allowed = array_keys(Plan::FEATURE_CATALOG);

        $data = $request->validate([
            'features' => ['present', 'array'],
            'features.*.feature' => ['required', 'string', Rule::in($allowed)],
            'features.*.valor_int' => ['nullable', 'integer', 'min:-1', 'max:1000000'],
            'features.*.valor_bool' => ['nullable', 'boolean'],
            'features.*.valor_str' => ['nullable', 'string', 'max:50'],
        ]);

        $catalog = Plan::FEATURE_CATALOG;

        // Normalizamos: dejamos un único valor según el tipo declarado
        // en el catálogo y descartamos features con todos los valores null.
        $toUpsert = [];
        foreach ($data['features'] as $entry) {
            $feature = $entry['feature'];
            $type = $catalog[$feature]['type'] ?? null;

            $valorInt = null;
            $valorBool = null;
            $valorStr = null;

            switch ($type) {
                case 'int':
                    $valorInt = isset($entry['valor_int']) ? (int) $entry['valor_int'] : null;
                    break;
                case 'bool':
                    if (array_key_exists('valor_bool', $entry) && $entry['valor_bool'] !== null) {
                        $valorBool = (bool) $entry['valor_bool'];
                    }
                    break;
                case 'str':
                    $valorStr = isset($entry['valor_str']) ? trim((string) $entry['valor_str']) : null;
                    if ($valorStr === '') {
                        $valorStr = null;
                    }
                    break;
            }

            if ($valorInt === null && $valorBool === null && $valorStr === null) {
                continue;
            }

            $toUpsert[$feature] = [
                'valor_int' => $valorInt,
                'valor_bool' => $valorBool,
                'valor_str' => $valorStr,
            ];
        }

        // Sync atómico: borramos lo que no llegó y upsert del resto.
        PlanFeature::query()
            ->where('plan_id', $plan->id)
            ->whereNotIn('feature', array_keys($toUpsert))
            ->delete();

        foreach ($toUpsert as $feature => $values) {
            PlanFeature::updateOrCreate(
                ['plan_id' => $plan->id, 'feature' => $feature],
                $values,
            );
        }

        $count = count($toUpsert);
        $message = $count === 0
            ? 'Se removieron todas las features del plan.'
            : ($count === 1
                ? '1 feature configurada en el plan.'
                : "{$count} features configuradas en el plan.");

        return back()->with('success', $message);
    }

    public function destroy(Plan $plan): RedirectResponse
    {
        // Si el plan tiene suscripciones, no permitimos borrarlo (la FK
        // tampoco lo permitiría: por defecto es ON DELETE RESTRICT).
        if ($plan->subscriptions()->exists()) {
            throw ValidationException::withMessages([
                'id' => 'No se puede eliminar un plan que tiene suscripciones. Desactívalo en su lugar.',
            ]);
        }

        $plan->delete();

        return back()->with('success', 'Plan eliminado correctamente.');
    }

    /**
     * Eliminación masiva. Filtra automáticamente los planes con
     * suscripciones para no romper la integridad referencial.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:200'],
            'ids.*' => ['uuid'],
        ]);

        $deletableIds = Plan::query()
            ->whereIn('id', $data['ids'])
            ->doesntHave('subscriptions')
            ->pluck('id')
            ->all();

        if (empty($deletableIds)) {
            return back()->with(
                'info',
                'No se eliminó ningún plan: todos los seleccionados tienen suscripciones asociadas.',
            );
        }

        $count = Plan::whereIn('id', $deletableIds)->delete();
        $skipped = count($data['ids']) - $count;

        $message = $count === 1
            ? '1 plan eliminado correctamente.'
            : "{$count} planes eliminados correctamente.";

        if ($skipped > 0) {
            $message .= sprintf(
                ' (%d plan%s con suscripciones se omitieron)',
                $skipped,
                $skipped === 1 ? '' : 'es',
            );
        }

        return back()->with('success', $message);
    }

    public function export(Request $request): StreamedResponse
    {
        $search = trim((string) $request->string('search', ''));

        $estado = (string) $request->string('estado', 'todos');
        if (! in_array($estado, self::ESTADO_OPTIONS, true)) {
            $estado = 'todos';
        }

        $sort = (string) $request->string('sort', '');
        $direction = strtolower((string) $request->string('direction', 'asc'));
        $sortValid = in_array($sort, self::SORTABLE_COLUMNS, true);
        $directionValid = in_array($direction, ['asc', 'desc'], true);

        $query = $this->buildBaseQuery($search, $estado);

        if ($sortValid) {
            $query->orderBy($sort, $directionValid ? $direction : 'asc');
            $query->orderBy('orden');
        } else {
            $query->orderBy('orden');
        }

        $filename = 'planes-'.now()->format('Ymd-His').'.xlsx';
        $exporter = new PlansXlsxExport;

        return response()->streamDownload(
            function () use ($exporter, $query) {
                $exporter->streamTo($query);
            },
            $filename,
            [
                'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
                'Pragma' => 'no-cache',
            ],
        );
    }

    /**
     * @return Builder<Plan>
     */
    private function buildBaseQuery(string $search, string $estado): Builder
    {
        $query = Plan::query();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('codigo', 'ILIKE', "%{$search}%")
                    ->orWhere('nombre', 'ILIKE', "%{$search}%")
                    ->orWhere('badge', 'ILIKE', "%{$search}%");
            });
        }

        switch ($estado) {
            case 'activos':
                $query->where('activo', true);
                break;
            case 'inactivos':
                $query->where('activo', false);
                break;
            case 'publicos':
                $query->where('es_publico', true);
                break;
            case 'privados':
                $query->where('es_publico', false);
                break;
        }

        return $query;
    }
}
